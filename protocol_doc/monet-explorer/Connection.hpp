/*
    Copyright 2020 Tamas Bolner
    
    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at
    
      http://www.apache.org/licenses/LICENSE-2.0
    
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
*/
#pragma once

#include <errno.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <netdb.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <strings.h>
#include <sstream>
#include <cstring>
#include "CommandLine.hpp"


namespace MonetExplorer {
    /**
     * @brief Represents the connection to the server.
     * Provides methods for sending and receiving text messages.
     * Solves the problem of handling packets.
     */
    class Connection {
        private:
            const int BUFFER_SIZE = 8192;
            int clientSocket = -1;
            bool connected = false;
            char *buffer;

            /**
             * @brief Blocks until the exact number of bytes is read.
             * 
             * @param byteCount The number of bytes to read. Must be less than
             * the buffer size.
             * @param throwError If false, then won't throw exception on read error.
             * @return int Returns byteCount on success. Returns 0 if
             * the connection was terminated on the server side.
             * Returns -1 on error and the errno is populated.
             */
            int ReadExact(int byteCount, bool throwError) {
                if (byteCount > BUFFER_SIZE) {
                    throw std::runtime_error("Connection::ReadExact(): byteCount is larger "
                        "than the buffer size.");
                }

                char *startPos = this->buffer;
                int remaining = byteCount;
                int response;

                do {
                    response = read(this->clientSocket, startPos, remaining);
                    if (response < 1) {
                        if (throwError && response < 0) {
                            throw std::runtime_error("Failed to read from the server. Error: '"
                                + std::string(strerror(errno)) + "' (" + std::to_string(errno) + ")");
                        }

                        return response;
                    }

                    remaining -= response;
                    startPos += response;
                } while (remaining > 0);

                return byteCount;
            }

            /**
             * @brief Blocks until the exact number of bytes
             * is written to the server.
             * 
             * @param byteCount The number of bytes to write. Must
             * be less than the buffer size.
             */
            void WriteExact(int byteCount) {
                if (byteCount > BUFFER_SIZE) {
                    throw std::runtime_error("Connection::WriteExact(): byteCount is larger "
                        "than the buffer size.");
                }

                char *startPos = this->buffer;
                int remaining = byteCount;
                int result;

                do {
                    result = write(this->clientSocket, startPos, remaining);
                    if (result < 1) {
                        if (errno != 0) {
                            throw std::runtime_error("Failed to write to server. Error: '"
                                + std::string(strerror(errno)) + "' (" + std::to_string(errno) + ")");
                        } else if (remaining > 0) {
                            usleep(100000);
                            continue;
                        }
                    }

                    remaining -= result;
                    startPos += result;
                } while (remaining > 0);
            }

        public:
            /**
             * @brief Construct a new Connection object
             */
            Connection() {
                this->buffer = new char[BUFFER_SIZE];
            }

            /**
             * @brief Destroy the Connection object
             */
            ~Connection() {
                this->Disconnect();

                delete[] this->buffer;
            }

            /**
             * @brief Disconnect from the server.
             * See: https://stackoverflow.com/a/8873013/6630230
             */
            void Disconnect() {
                if (!this->connected) {
                    // Already disconnected.
                    return;
                }

                // Close the outgoing channel
                shutdown(this->clientSocket, SHUT_WR);

                /*
                    After the server noticed that the client
                    closed its outgoing channel, it will also
                    do so. Read until that is detected.
                */
                while(this->ReadExact(BUFFER_SIZE, false) > 0);
                close(this->clientSocket);

                this->connected = false;
            }

            /**
             * @brief Connect to a server through TCP/IP.
             * 
             * @param host Host name of the server.
             * @param port Port of the server.
             */
            void ConnectTCP(std::string host, int port) {
                if (this->connected) {
                    throw std::runtime_error("Connection::ConnectTCP(): Already connected to the server. "
                        "(Method is called twice.)");
                }

                /*
                    Connect through TCP/IP.
                */
                std::cout << "\033[32mConnecting through TCP/IP to: " << host << ':'
                    << port << "\033[0m\n";

                this->clientSocket = socket(AF_INET, SOCK_STREAM, 0);
                if (this->clientSocket == -1) {
                    throw std::runtime_error("Failed to create socket. Error: '" + std::string(strerror(errno))
                        + "' (" + std::to_string(errno) + ")");
                }

                struct sockaddr_in serverAddress;
                bzero(&serverAddress, sizeof(serverAddress));

                serverAddress.sin_family = AF_INET;
                serverAddress.sin_addr.s_addr = inet_addr(host.c_str());
                serverAddress.sin_port = htons(port);

                if (connect(this->clientSocket, (sockaddr*)&serverAddress, sizeof(serverAddress)) != 0) {
                    throw std::runtime_error("Failed to connect to the server. Error: '"
                        + std::string(strerror(errno)) + "' (" + std::to_string(errno) + ")");
                }

                this->connected = true;
            }

            /**
             * @brief Connect to the server though Unix
             * domain socket.
             * 
             * @param port The port of the server is part of the
             * name of the files which represent these sockets.
             * Therefore it's required for finding the files.
             */
            void ConnectUnix(int port) {
                if (this->connected) {
                    throw std::runtime_error("Connection::ConnectUnix(): Already connected to the server. "
                        "(Method is called twice.)");
                }

                std::cout << "\033[32mConnecting through Unix domain socket.\033[0m\n";

                this->clientSocket = socket(AF_UNIX, SOCK_STREAM, 0);
                if (this->clientSocket == -1) {
                    throw std::runtime_error("Failed to create socket. Error: '" + std::string(strerror(errno))
                        + "' (" + std::to_string(errno) + ")");
                }

                std::vector<std::string> paths ({
                    "/tmp/.s.monetdb." + std::to_string(port)
                });

                for(const std::string &path : paths) {
                    std::cout << "\033[32mTrying: " << path << "\033[0m\n";

                    struct sockaddr_un serverAddress;
                    bzero(&serverAddress, sizeof(serverAddress));
                    serverAddress.sun_family = AF_UNIX;
                    std::memcpy(serverAddress.sun_path, path.c_str(), path.length() + 1);

                    if (connect(this->clientSocket, (sockaddr*)&serverAddress, sizeof(serverAddress)) == 0) {
                        // See: https://github.com/MonetDB/MonetDB/blob/1f1bbdbd3340fdb74345723e8c98c120dcaf2ead/clients/mapilib/mapi.c#L2416
                        this->buffer[0] = '0';
                        this->WriteExact(1);
                        this->connected = true;
                        return;
                    }
                }

                throw std::runtime_error("Failed to connect to the server. Error: '"
                    + std::string(strerror(errno)) + "' (" + std::to_string(errno) + ")");
            }

            /**
             * @brief Returns true if the client is connected
             * to the MonetDB server, false otherwise.
             * 
             * @return bool
             */
            bool IsConnected() {
                return this->connected;
            }

            /**
             * @brief Receive a message from the MonetDB server.
             * 
             * @return std::string 
             */
            std::string ReceiveMessage() {
                int response;
                uint16_t header;
                bool isLastPacket;
                int payloadSize;
                std::stringstream message;

                do {
                    /*
                        Read header
                    */
                    response = this->ReadExact(2, true);
                    if (response == 0) {
                        // Server closed the connection.
                        this->Disconnect();
                        return message.str();
                    }

                    header = *((uint16_t*)this->buffer);
                    isLastPacket = header & (uint16_t)1;
                    payloadSize = header >> 1;
                    if (payloadSize > BUFFER_SIZE - 2) {
                        throw std::runtime_error("A packet returned from the server had larger than "
                            + std::to_string(BUFFER_SIZE - 2) + " bytes payload. " + std::to_string(payloadSize));
                    }

                    /*
                        Read payload
                    */
                    if (payloadSize > 0) {
                        response = this->ReadExact(payloadSize, true);
                        if (response == 0) {
                            // Server closed the connection.
                            this->Disconnect();
                            return message.str();
                        }

                        message.write(this->buffer, payloadSize);
                    }
                } while (!isLastPacket);

                return message.str();
            }

            /**
             * @brief Send a message to the MonetDB server.
             * 
             * @param message 
             */
            void SendMessage(std::string &message) {
                const char *pos = message.c_str();
                int remaining = message.length();
                int packetSize;

                do {
                    if (remaining < BUFFER_SIZE - 2) {
                        *((uint16_t*)this->buffer) = ((uint16_t)remaining << 1) | (uint16_t)1;
                        packetSize = remaining;
                    } else {
                        *((uint16_t*)this->buffer) = ((uint16_t)BUFFER_SIZE - (uint16_t)2) << 1;
                        packetSize = BUFFER_SIZE - 2;
                    }

                    std::memcpy(this->buffer + 2, pos, packetSize);
                    this->WriteExact(packetSize + 2);

                    remaining -= packetSize;
                    pos += packetSize;
                } while (remaining > 0);
            }
    };
}
