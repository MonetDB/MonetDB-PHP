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

/**
 * @brief Description of an argument
 */
class CommandLineArg {
    private:
        std::string name;
        std::string type;
        std::string def;
        std::string description;

    public:
        /**
         * @brief Construct a new Command Line Arg object
         * 
         * @param name Name of the argument.
         * @param type Possible types are: string, bool, int.
         * @param def The string representation of the default value.
         * @param description The description of the argument.
         */
        CommandLineArg(std::string name, std::string type, std::string def, std::string description)
            : name(name), type(type), def(def), description(description) { }
        
        std::string GetName() {
            return this->name;
        }
};


/**
 * @brief Parse command line arguments.
 */
class CommandLine {

};
