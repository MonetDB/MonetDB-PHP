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
                this->connection.Connect(
                    args.GetStringValue("host"),
                    args.GetIntValue("port")
                );

                std::string msg;

                std::cout << '\n';

                /*
                    Authentication
                */
                for (int i = 0; i <= 10; i++) {
                    if (i == 10) {
                        throw std::runtime_error("Authentication failed: Too many Merovingian redirects.");
                    }

                    msg = this->connection.ReceiveMessage();
                    std::cout << "\nReceived:\n" << msg << std::endl;

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
                        args.GetStringValue("database")
                    );
                    std::cout << "\nSent:\n" << msg << std::endl;

                    this->connection.SendMessage(msg);
                }

                /*
                    Communication
                */
                while(true) {
                    std::cout << "Enter message to send:\n";
                    std::getline(std::cin, msg);
                    this->connection.SendMessage(msg);
                    
                    msg = this->connection.ReceiveMessage();
                    std::cout << "Response:\n" << msg << "\n";
                }
            }
    };
}
