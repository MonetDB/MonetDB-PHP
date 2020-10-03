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

#include "CommandLine.hpp"
#include "Client.hpp"


int main(int argc, char *argv[]) {
    try {
        /*
            Parse command line arguments
        */
        CommandLine::Parser cmd(argc, argv);

        cmd.Argument.String("host", 'h', "127.0.0.1", "host_name", "The host name or IP add|ress "
            "of the \033[1mMonetDB server\033[0m.");
        cmd.Argument.Int("port", 'p', 50000, "port", "The port of the \033[1mMonetDB server\033[0m.");
        cmd.Argument.String("user", 'u', "monetdb", "user_name", "User name for the database login.");
        cmd.Argument.String("password", 'P', "monetdb", "password", "User password for the database login.");
        cmd.Operand("database", "The name of the data|base to connect to.");
        cmd.Option("unix-domain-socket", 'x', "Use a unix domain socket for connect|ing "
            "to the \033[1mMonetDB server\033[0m, instead of connect|ing through TCP/IP. "
            "If pro|vi|ded, then the host and port ar|gu|ments are ig|no|red.");
        cmd.Option("file-transfer", 't', "Enable the file trans|fer pro|to|col for the connect|ion.");
        cmd.Argument.String("auth-algo", 'a', "SHA1", "algo", "The hash al|go|rithm to be used "
            "for the 'salted hashing'. The \033[1mMonetDB server\033[0m has to support it. This is "
            "typi|cally a weaker hash al|go|rithm, which is used to|gether with a "
            "stron|ger 'pass|word hash' that is currently SHA512.");
        cmd.Option("help", '?', "Display the usage instructions.");
        cmd.RestrictOperands();

        auto args = cmd.Parse();

        if (args.IsHelpRequested()) {
            std::cout << "\n\n Some text \n\n";
            std::cout << cmd.GenerateDoc();
            return 0;
        }

        /*
            Start the client
        */
        Client client(args);
        client.Start();
        
    } catch (const std::runtime_error &err) {
        std::cerr << "\n" << err.what() << "\n\n";
        return 1;
    }

    return 0;
}
