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

#include <string>
#include <unordered_set>
#include <vector>
#include <sstream>
#include <openssl/sha.h>


namespace MonetExplorer {
    /**
     * @brief Parse a "server challenge" line, which
     * can be received multiple time from the server
     * during authentication.
     */
    class ServerChallenge {
        private:
            std::string salt;
            std::string backend;
            int version;
            std::unordered_set<std::string> protocols;
            std::string endianness;
            std::string passwordHashAlgo;
            const char *hexa = "0123456789abcdef";

            /**
             * @brief Converts binary data to hex.
             * 
             * @param source The source buffer.
             * @param size The size of the source buffer.
             * @param dest The destination buffer. It requires double the size. (2 x size)
             */
            void BinToHex(char *source, int size, char *dest) {
                for (int i = 0; i < size; i++) {
                    dest[2 * i + 1] = hexa[source[i] & 15];
                    dest[2 * i] = hexa[(source[i] >> 4) & 15];
                }
            }

            std::string Sha512(std::string data) {
                char bin[64];
                char hex[128];

                SHA512((unsigned char*)data.c_str(), data.length(), (unsigned char*)bin);
                this->BinToHex(bin, 64, hex);

                return std::string(hex, 128);
            }

            std::string Sha1(std::string data) {
                char bin[20];
                char hex[40];

                SHA1((unsigned char*)data.c_str(), data.length(), (unsigned char*)bin);
                this->BinToHex(bin, 20, hex);

                return std::string(hex, 40);
            }

        public:
            /**
             * @brief Construct a new Server Challenge object
             * 
             * @param msg The message received from the server.
             */
            ServerChallenge(std::string &msg) : salt(), backend(), version(0), protocols(),
                    endianness(), passwordHashAlgo() {

                if (msg.length() < 1) {
                    throw std::runtime_error("Empty message received. Expected server challenge.");
                }
                
                const char *pos = msg.c_str();
                const char *start = pos;
                const char *endPos = pos + msg.length() - 1;
                int field = 0;

                for(; pos < endPos; pos++) {
                    if (*pos == ':' || pos == endPos - 1 || *pos == '\n' || *pos == ',') {
                        switch (field) {
                            case 0: {
                                this->salt = std::string(start, pos - start);
                                if (this->salt.length() < 6) {
                                    throw std::runtime_error("Too short salt value received "
                                        "in the server challenge line: " + this->salt);
                                }
                                break;
                            }
                            case 1: {
                                this->backend = std::string(start, pos - start);
                                if (this->backend != "merovingian" && this->backend != "monetdb"
                                        && this->backend != "mserver") {
                                    
                                    throw std::runtime_error("Invalid backend value received "
                                        "in the server challenge line: " + this->backend);
                                }
                                break;
                            }
                            case 2: {
                                std::string tmp(start, pos - start);
                                char *endPtr;
                                this->version = strtol(tmp.c_str(), &endPtr, 10);
                                if (errno != 0 || *endPtr != '\0') {
                                    throw std::runtime_error("Invalid version value received "
                                        "in the server challenge line: " + tmp);
                                }
                                
                                break;
                            }
                            case 3: {
                                std::string proto(start, pos - start);
                                if (proto.length() < 1) {
                                    throw std::runtime_error("Invalid protocol name received "
                                        "in the server challenge line. (empty value)");
                                }
                                this->protocols.insert(proto);
                                break;
                            }
                            case 4: {
                                this->endianness = std::string(start, pos - start);
                                if (this->endianness != "LIT") {
                                    throw std::runtime_error("The server challenge line offered "
                                        "endianness '" + this->endianness + "', but only "
                                        "LIT (little endian) is accepted.");
                                }
                                break;
                            }
                            case 5: {
                                this->passwordHashAlgo = std::string(start, pos - start);
                                if (this->passwordHashAlgo.length() < 1) {
                                    throw std::runtime_error("Invalid password hash algo received "
                                        "in the server challenge line. (empty value)");
                                }
                                break;
                            }
                        }

                        if (*pos == ':') {
                            if (field >= 5) {
                                break;
                            }

                            field++;
                        }

                        start = pos + 1;
                    }
                }

                if (field < 5) {
                    throw std::runtime_error("The server challenge line contained less than 5 fields.");
                }
            }

            /**
             * @brief Generates the response message to the server
             * challenge, for the authentication.
             * 
             * @param user MonetDB user name.
             * @param password User password.
             * @param database The name of the database to connect to.
             * @return std::string 
             */
            std::string Authenticate(std::string user, std::string password, std::string database) {
                std::stringstream buff;
                std::string pwHash = this->Sha1(this->Sha512(password) + this->salt);
                
                buff << this->endianness << ':' << user << ':' << "{SHA1}" << pwHash 
                    << ":sql:" << database << ":\n";

                return buff.str();
            }
    };
}
