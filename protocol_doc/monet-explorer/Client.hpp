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

#include "CommandLine.hpp"
#include "Connection.hpp"
#include "ServerChallenge.hpp"

namespace MonetExplorer {
    /**
     * @brief Main logic of the client application.
     */
    class Client {
        private:
            CommandLine::Arguments &args;
            Connection connection;

            /**
             * @brief Format a message for the console output.
             * Highlight special characters with colors, etc.
             * Supports UTF-8.
             * 
             * @param msg The message to be formatted.
             * @param isSent True = sent to the server, False = received from it.
             * @param output Most probably std::cout.
             */
            void PrintFormatted(std::string &msg, bool isSent, std::ostream &output) {
                int mb_remain = 0;  // Bytes remaining from a multi-byte character
                const char *pos = msg.c_str();
                const char *endPos = pos + msg.length();
                char c = ' ';

                if (isSent) {
                    output << "\033[32mSent:\033[0m\n";
                } else {
                    output << "\033[32mReceived:\033[0m\n";
                }

                for(; pos < endPos; pos++) {
                    c = *pos;

                    /*
                        Handle UTF-8
                        https://stackoverflow.com/a/44568131/6630230
                    */
                    if (mb_remain > 0) {
                        // Check for UTF-8 errors
                        if ((c & 0xC0) != 0x80) {
                            // Non-expected byte header
                            //  => Treat it as a new character
                            mb_remain = 0;
                            goto utf8_checks;
                        }

                        output << c;
                        continue;
                    }

                    utf8_checks:
                    
                    if ((c & 0xE0) == 0xC0) {
                        mb_remain = 1;
                        output << c;
                        continue;
                    } else if ((c & 0xF0) == 0xE0) {
                        mb_remain = 2;
                        output << c;
                        continue;
                    } else if ((c & 0xF8) == 0xF0) {
                        mb_remain = 3;
                        output << c;
                        continue;
                    }

                    /*
                        Printable characters
                    */
                    if (isprint(c)) {
                        output << c;
                        continue;
                    }

                    /*
                        Control and other special characters.
                    */
                    output << "\033[44m\033[97m";   // bg, fg

                    if (c == '\n') {
                        output << "\\n";
                    }
                    else if (c == '\t') {
                        output << "\\t";
                    }
                    else if (c == '\r') {
                        output << "\\r";
                    }
                    else if (c == '\f') {
                        output << "\\f";
                    }
                    else {
                        // Octal codes for all the others
                        output << "\\";

                        for(int shift = 6; shift >= 0; shift -= 3) {
                            output << (char)('0' + ((c >> shift) & 7));
                        }
                    }

                    output << "\033[0m";

                    if (c == '\n') {
                        output << c;
                    }
                }

                if (c != '\n') {
                    output << '\n';
                }
            }

        public:
            /**
             * @brief Construct a new Client object
             * 
             * @param args Command line arguments.
             */
            Client(CommandLine::Arguments &args) : args(args), connection() { }

            /**
             * @brief Start the client application.
             */
            void Start() {
                if (args.GetStringValue("database") == "") {
                    throw std::runtime_error("Please specify a database to connect to.");
                }

                /*
                    Connect to the server
                */
                if (args.IsOptionSet("unix-domain-socket")) {
                    this->connection.ConnectUnix(
                        args.GetIntValue("port")
                    );
                } else {
                    this->connection.ConnectTCP(
                        args.GetStringValue("host"),
                        args.GetIntValue("port")
                    );
                }

                std::cout << "\033[32mConnected.\033[0m\n";                
                std::string msg;

                /*
                    Authentication
                */
                for (int i = 0; i <= 10; i++) {
                    if (i == 10) {
                        throw std::runtime_error("Authentication failed: Too many Merovingian redirects.");
                    }

                    msg = this->connection.ReceiveMessage();
                    this->PrintFormatted(msg, false, std::cout);

                    if (msg.rfind("^mapi:merovingian:", 0) == 0) {
                        // Merovingian redirect
                        continue;
                    } else if (msg == "") {
                        // Successful authentication
                        break;
                    } else if (msg.rfind("!", 0) == 0) {
                        throw std::runtime_error("Authentication failed: " + msg);
                    }

                    ServerChallenge challenge(msg);
                    msg = challenge.Authenticate(
                        args.GetStringValue("user"),
                        args.GetStringValue("password"),
                        args.GetStringValue("database"),
                        args.GetStringValue("auth-algo"),
                        args.IsOptionSet("file-transfer")
                    );
                    this->PrintFormatted(msg, true, std::cout);

                    this->connection.SendMessage(msg);
                }

                /*
                    Communication
                */
                do {
                    std::stringstream multiLine;
                    std::cout << "\033[32mEnter message:\033[0m\n";
                    while(true) {
                        std::getline(std::cin, msg);
                        if (msg != "") {
                            multiLine << msg << '\n';
                        } else {
                            break;
                        }
                    }
                    
                    msg = multiLine.str();
                    this->connection.SendMessage(msg);

                    msg = this->connection.ReceiveMessage();
                    this->PrintFormatted(msg, false, std::cout);
                } while (this->connection.IsConnected());

                std::cout << "\033[32mServer disconnected.\033[0m\n";
            }
    };
}
