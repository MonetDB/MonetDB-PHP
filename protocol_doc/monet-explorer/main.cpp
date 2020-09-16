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

        cmd.Argument.String("host", 'h', "127.0.0.1", "The host name or IP address "
            "of the MonetDB server.");
        cmd.Argument.Int("port", 'p', 50000, "The port of the MonetDB server.");
        cmd.Argument.String("user", 'u', "monetdb", "User name.");
        cmd.Argument.String("password", 'P', "monetdb", "User password.");
        cmd.Operand("database", "The name of the database to connect to.");
        cmd.Option("unix-domain-socket", 'x', "Use a unix domain socket for connecting "
            "to the MonetDB server, instead of connecting through TCP/IP. "
            "If provided, then the host and port arguments are ignored.");
        cmd.Option("file-transfer", 't', "Enable the file transfer protocol for the connection.");
        cmd.Argument.String("auth-algo", 'a', "SHA1", "The hash algorithm to be used "
            "for the 'salted hashing'. The MonetDB server has to support it. This is "
            "typically a weaker hash algorithm, which is used together with a "
            "stronger 'password hash' that is currently SHA512.");
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
