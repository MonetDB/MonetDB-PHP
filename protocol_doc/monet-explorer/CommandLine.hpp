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

#include <ctype.h>
#include <errno.h>
#include <stdlib.h>
#include <iostream>
#include <string>
#include <sstream>
#include <set>
#include <vector>
#include <map>
#include <unordered_map>

namespace CommandLine {
    namespace Helper {
        /**
         * @brief Argument type
         */
        enum class ArgumentType : int {
            String = 1,
            Int = 2,
            Double = 3,
            Boolean = 4
        };

        /**
         * @brief Classes of the arguments
         */
        enum class ArgumentClass : int {
            Argument = 1,
            Option = 2,
            Operand = 3
        };

        /**
         * @brief Properties of an argument
         */
        class CommandLineArg {
            private:
                std::string name;
                char letter;
                ArgumentClass argClass;
                ArgumentType argType;
                bool optional;
                std::string stringDefault;
                int intDefault;
                double doubleDefault;
                std::string description;
                
            public:
                /**
                 * @brief Create empty ebject
                 */
                CommandLineArg() 
                    : name(), letter(0), argClass(ArgumentClass::Argument), argType(ArgumentType::String),
                    optional(false), stringDefault(), intDefault(0), doubleDefault(0), description()
                { }

                /**
                 * @brief Create a new Operand
                 * 
                 * @param name Name of the operand.
                 * @param description The description of the operand.
                 */
                CommandLineArg(std::string name, std::string description) 
                    : name(name), letter(0), argClass(ArgumentClass::Operand), argType(ArgumentType::String),
                    optional(false), stringDefault(), intDefault(0), doubleDefault(0), description(description)
                { }

                /**
                 * @brief Create a new Option
                 * 
                 * @param name Name of the option.
                 * @param letter The single-character name of the option.
                 * @param description Description of the option.
                 */
                CommandLineArg(std::string name, char letter, std::string description)
                    : name(name), letter(letter), argClass(ArgumentClass::Option), argType(ArgumentType::Boolean),
                    optional(false), stringDefault(), intDefault(0), doubleDefault(0), description(description)
                { }

                /**
                 * @brief Create a mandatory argument without a default.
                 * 
                 * @param name Name of the argument.
                 * @param letter The single-character name of the argument.
                 * @param argType 
                 * @param description 
                 */
                CommandLineArg(std::string name, char letter, ArgumentType argType, std::string description)
                    : name(name), letter(letter), argClass(ArgumentClass::Argument), argType(argType),
                    optional(false), stringDefault(), intDefault(0), doubleDefault(0), description(description)
                { }

                /**
                 * @brief Create an optional integer argument with a default.
                 * 
                 * @param name Name of the argument.
                 * @param letter The single-character name of the argument.
                 * @param intDefault 
                 * @param description 
                 */
                CommandLineArg(std::string name, char letter, int intDefault, std::string description)
                    : name(name), letter(letter), argClass(ArgumentClass::Argument), argType(ArgumentType::Int),
                    optional(true), stringDefault(), intDefault(intDefault), doubleDefault(0), description(description)
                { }

                /**
                 * @brief Create an optional string argument with a default.
                 * 
                 * @param name Name of the argument.
                 * @param letter The single-character name of the argument.
                 * @param stringDefault 
                 * @param description 
                 */
                CommandLineArg(std::string name, char letter, std::string stringDefault, std::string description)
                    : name(name), letter(letter), argClass(ArgumentClass::Argument), argType(ArgumentType::String),
                    optional(true), stringDefault(stringDefault), intDefault(0), doubleDefault(0), description(description)
                { }

                /**
                 * @brief Create an optional double argument with a default.
                 * 
                 * @param name Name of the argument.
                 * @param letter The single-character name of the argument.
                 * @param doubleDefault 
                 * @param description 
                 */
                CommandLineArg(std::string name, char letter, double doubleDefault, std::string description)
                    : name(name), letter(letter), argClass(ArgumentClass::Argument), argType(ArgumentType::Double),
                    optional(true), stringDefault(), intDefault(0), doubleDefault(doubleDefault), description(description)
                { }

                /**
                 * @brief Get the name of the argument.
                 * 
                 * @return std::string 
                 */
                std::string GetName() {
                    return this->name;
                }

                /**
                 * @brief Get the single-character name of the argument.
                 * 
                 * @return char 
                 */
                char GetLetter() {
                    return this->letter;
                }

                /**
                 * @brief Get the class of the argument. (argument, option, operand.)
                 * 
                 * @return ArgumentClass 
                 */
                ArgumentClass GetArgClass() {
                    return this->argClass;
                }

                /**
                 * @brief Get the type of the argument
                 * 
                 * @return ArgumentType
                 */
                ArgumentType GetArgType() {
                    return this->argType;
                }

                /**
                 * @brief Is this parameter optional.
                 * 
                 * @return bool
                 */
                bool IsOptional() {
                    return this->optional;
                }

                /**
                 * @brief Get the string default value.
                 * 
                 * @return std::string 
                 */
                std::string GetStringDefault() {
                    return this->stringDefault;
                }

                /**
                 * @brief Get the int default value.
                 * 
                 * @return int
                 */
                int GetIntDefault() {
                    return this->intDefault;
                }

                /**
                 * @brief Get the double default value.
                 * 
                 * @return double
                 */
                int GetDoubleDefault() {
                    return this->doubleDefault;
                }

                /**
                 * @brief Get the description of the argument.
                 * 
                 * @return std::string 
                 */
                std::string GetDescription() {
                    return this->description;
                }
        };

        /**
         * @brief For accumulating the definitions and values of
         * all arguments.
         */
        class ArgumentAccumulator {
            public:
                bool restrictOperands;
                std::string executableName;
                std::map<std::string, CommandLineArg> argsByName;
                std::map<char, CommandLineArg> argsByLetter;
                std::vector<CommandLineArg> operands;

                std::unordered_map<std::string, std::string> stringValues;
                std::unordered_map<std::string, int> intValues;
                std::unordered_map<std::string, double> doubleValues;
                std::set<std::string> optionNames;
                std::vector<std::string> operandValues;

                /**
                 * @brief Construct a new Argument Accumulator object
                 */
                ArgumentAccumulator() : restrictOperands(false), executableName(), argsByName(), argsByLetter(),
                    operands(), stringValues(), intValues(), doubleValues(), optionNames(), operandValues() { }

                /**
                 * @brief Specify a new argument
                 * 
                 * @param arg 
                 */
                void AddArg(CommandLineArg arg) {
                    if (argsByName.find(arg.GetName()) != argsByName.end()) {
                        throw std::runtime_error("Two different arguments have the same name: '" + arg.GetName() + "'.");
                    }

                    argsByName.insert({ arg.GetName(), arg });

                    if (arg.GetArgClass() == ArgumentClass::Operand) {
                        operands.push_back(arg);
                    }

                    if (arg.GetLetter() != 0) {
                        if (argsByLetter.find(arg.GetLetter()) != argsByLetter.end()) {
                            throw std::runtime_error("Two different arguments have the same one-letter name: '"
                                + std::string(1, arg.GetLetter()) + "'.");
                        }

                        argsByLetter.insert({ arg.GetLetter(), arg});
                    }

                    /*
                        Store default
                    */
                    if (arg.IsOptional()) {
                        if (arg.GetArgType() == ArgumentType::Double) {
                            this->doubleValues.insert({arg.GetName(), arg.GetDoubleDefault()});
                        }
                        else if (arg.GetArgType() == ArgumentType::Int) {
                            this->intValues.insert({arg.GetName(), arg.GetIntDefault()});
                        }
                        else {
                            this->stringValues.insert({arg.GetName(), arg.GetStringDefault()});
                        }
                    }
                }

                /**
                 * @brief Converts the value to the proper type
                 * and stores it.
                 * 
                 * @param arg 
                 * @param value 
                 */
                void SetValue(CommandLineArg arg, std::string value) {
                    if (arg.GetArgType() == ArgumentType::Int) {
                        char *endPtr;

                        int result = strtol(value.c_str(), &endPtr, 10);
                        if (errno == ERANGE) {
                            throw std::runtime_error("Integer value out of range.");
                        }
                        else if (*endPtr != '\0' || errno != 0) {
                            throw std::runtime_error("Invalid integer value.");
                        }

                        this->intValues.insert({ arg.GetName(), result });
                    }
                    else if (arg.GetArgType() == ArgumentType::Double) {
                        char *endPtr;

                        double result = strtod(value.c_str(), &endPtr);
                        if (errno == ERANGE) {
                            throw std::runtime_error("Double value out of range.");
                        }
                        else if (*endPtr != '\0' || errno != 0) {
                            throw std::runtime_error("Invalid Double value.");
                        }

                        this->doubleValues.insert({ arg.GetName(), result });
                    }
                    else {
                        this->stringValues.insert({arg.GetName(), value});
                    }
                }
        };

        /**
         * @brief Provides a user-friendly interface
         * for specifying arguments of different types.
         */
        class ArgumentSpecifier {
            private:
                ArgumentAccumulator &accu;

            public:
                /**
                 * @brief Construct a new ArgumentSpecifier object
                 * 
                 * @param accu 
                 */
                ArgumentSpecifier(ArgumentAccumulator &accu) : accu(accu) { }

                /**
                 * @brief Optional argument with integer value and
                 * a default.
                 * 
                 * @param name 
                 * @param letter 
                 * @param defaultValue 
                 * @param description 
                 */
                void Int(const char *name, char letter, int defaultValue, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, defaultValue, description));
                }

                /**
                 * @brief Mandatory argument with integer value.
                 * 
                 * @param name 
                 * @param letter 
                 * @param description 
                 */
                void Int(const char *name, char letter, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, ArgumentType::Int, description));
                }

                /**
                 * @brief Optional argument with string type and
                 * default value.
                 * 
                 * @param name 
                 * @param letter 
                 * @param defaultValue 
                 * @param description 
                 */
                void String(const char *name, char letter, const char *defaultValue, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, defaultValue, description));
                }

                /**
                 * @brief Mandatory argument with string type.
                 * 
                 * @param name 
                 * @param letter 
                 * @param description 
                 */
                void String(const char *name, char letter, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, ArgumentType::String, description));
                }

                /**
                 * @brief Optional argument with double type and
                 * default value.
                 * 
                 * @param name 
                 * @param letter 
                 * @param defaultValue 
                 * @param description 
                 */
                void Double(const char *name, char letter, double defaultValue, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, defaultValue, description));
                }

                /**
                 * @brief Mandatory argument with double type.
                 * 
                 * @param name 
                 * @param letter 
                 * @param description 
                 */
                void Double(const char *name, char letter, const char *description) {
                    accu.AddArg(CommandLineArg(name, letter, ArgumentType::Double, description));
                }
        };
    }

    /**
     * @brief Provides a user-friendly interface
     * to query argument values and related info.
     */
    class Arguments {
        private:
            /**
             * @brief Encapsulates the actual data.
             */
            Helper::ArgumentAccumulator &accu;

        public:
            /**
             * @brief Construct a new Arguments object
             * 
             * @param accu 
             */
            Arguments(Helper::ArgumentAccumulator &accu) : accu(accu) { }

            /**
             * @brief Returns true if no arguments were
             * provided or if the "--help" argument is
             * present.
             * 
             * @return bool
             */
            bool IsHelpRequested() {
                if (this->accu.operands.size() < 1 && this->accu.optionNames.size() < 1
                    && this->accu.argsByName.size() < 1) {
                    
                    return true;
                }

                if (this->accu.optionNames.find(std::string("help")) != this->accu.optionNames.end()) {
                    return true;
                }

                return false;
            }
    };

    /**
     * @brief Parse command line arguments.
     */
    class Parser {
        private:
            int argc;
            char **argv;
            Helper::ArgumentAccumulator accu;

            /**
             * @brief Trim C string. Designed for argv. Pointers and strings in argv
             * can be modified freely according to the C99 standard.
             * 
             * @param str This function modifies both the pointer and the ending.
             * @return The size of the string in bytes.
             */
            int Trim(char **str) {
                left_trim:
                
                if (**str == '\0') {
                    return 0;
                }

                if (!isprint(**str)) {
                    (*str)++;
                    goto left_trim;
                }

                // Find the ending
                char *end = *str;
                do {
                    end++;
                }
                while(*end != '\0');

                right_trim:

                end--;  // at first: end > *str

                if (!isprint(*end)) {
                    *end = '\0';

                    if (end > *str) {
                        goto right_trim;
                    } else {
                        return 0;
                    }
                }

                return (int)(end - *str) + 1;
            }

            /**
             * @brief Creates a detailed error string, including
             * the reconstructed command line, and an arrow
             * pointing to the problem.
             * 
             * @param message 
             * @param line 
             * @param position 
             */
            void ThrowError(std::string message, std::string line, int position) {
                std::stringstream buff;
                const int windowSize = 80;
                const float ratio = 0.666;
                const int maxHead = (int)(ratio * (float)windowSize);
                const int maxTail = windowSize - maxHead;
                int start, head, length;

                /*
                    The 4 cases are explained here:
                        - protocol_doc/monet-explorer/docs/error_display_cases.png
                */

                if (position < maxHead || line.size() < windowSize) {
                    // Cases 2 and 4
                    start = 0;
                    head = position;
                    length = std::min(windowSize, (int)line.size());
                }
                else if ((int)line.size() - position < maxTail) {
                    // Case 3
                    start = line.size() - windowSize;
                    head = position - start;
                    length = windowSize;
                }
                else {
                    // Case 1
                    start = position - maxHead;
                    head = maxHead;
                    length = windowSize;
                }

                buff << message << "\n\n";
                buff << line.substr(start, length) << "\n";
                buff << std::string(head + 1, ' ') << "^\n";
                buff << std::string(head + 1, ' ') << "|\n";

                throw std::runtime_error(buff.str());
            }

            /**
             * @brief Add a new operand. Throw error if there are
             * too many operands.
             * 
             * @param value 
             */
            void AddOperand(char *value) {
                if (this->accu.operandValues.size() > this->accu.operands.size()
                    && this->accu.restrictOperands) {

                    throw std::runtime_error("The maximal number of operands is restricted to "
                        + std::to_string(this->accu.operands.size()) + ".");
                }

                this->accu.operandValues.push_back(std::string(value));
            }

        public:
            /**
             * @brief Specify a new argument.
             */
            Helper::ArgumentSpecifier Argument;

            /**
             * @brief Construct a new Parser object
             * 
             * @param argc First parameter of the main function.
             * @param argv Second parameter of the main function.
             */
            Parser(int argc, char *argv[]) : argc(argc), argv(argv), accu(), Argument(accu) { }

            /**
             * @brief Specify a new option.
             * 
             * @param name The name of the option.
             * @param letter The one-character name of the option.
             * @param description The description of the option.
             */
            void Option(const char *name, char letter, const char *description) {
                this->accu.AddArg(Helper::CommandLineArg(name, letter, description));
            }

            /**
             * @brief Specify a new operand.
             * 
             * @param name The name of the operand.
             * @param description The description of the operand.
             */
            void Operand(const char *name, const char *description) {
                this->accu.AddArg(Helper::CommandLineArg(name, description));
            }

            /**
             * @brief Call this to prohibit the users to
             * provide arbitrary number of operands.
             * They'll only be able to pass operands of
             * the specified amount.
             */
            void RestrictOperands() {
                this->accu.restrictOperands = true;
            }

            /**
             * @brief Parse the command line arguments
             * and return them in a user-friendly
             * "Arguments" object.
             * Call this only after all arguments
             * are specified.
             * 
             * @return Arguments 
             */
            Arguments Parse() {
                bool expectArgValue = false;
                std::string argName;
                std::stringstream line; // For error messages
                Helper::CommandLineArg lastArg;

                for(int i = 0; i < this->argc; i++) {
                    int position = line.tellp();
                    int length = this->Trim(&(this->argv[i]));
                    char *arg = this->argv[i];

                    if (position > 0) {
                        line << " " << arg;
                    }
                    else {
                        line << arg;
                    }

                    if (i == 0) {
                        this->accu.executableName = arg;
                        continue;
                    }

                    try {
                        if (length == 0) {
                            // Ignore empty argument
                            continue;
                        }
                        else if (expectArgValue) {
                            if (arg[0] == '-' && length > 1) {
                                throw std::runtime_error("Expected an argument value, but the data starts with a dash "
                                    "character. If you wish to provide a string that starts with dash, "
                                    "then please put the string into quotes (single or double quotes).");
                            }
                            else if (arg[0] == '"') {
                                if (arg[length - 1] == '"' && length > 1) {
                                    /*
                                        Argument value inside quotes
                                    */
                                    arg[length - 1] = '\0';
                                    this->accu.SetValue(lastArg, std::string(arg + 1));
                                    expectArgValue = false;
                                    continue;
                                }
                                else {
                                    throw std::runtime_error("Missing closing quote at the end of the argument value.");
                                }
                            }
                            else if (arg[0] == '\'') {
                                if (arg[length - 1] == '\'' && length > 1) {
                                    /*
                                        Argument value inside quotes
                                    */
                                    arg[length - 1] = '\0';
                                    this->accu.SetValue(lastArg, std::string(arg + 1));
                                    expectArgValue = false;
                                    continue;
                                }
                                else {
                                    throw std::runtime_error("Missing closing quote at the end of the argument value.");
                                }
                            }
                            else {
                                /*
                                    Argument value
                                */
                                this->accu.SetValue(lastArg, std::string(arg));
                                expectArgValue = false;
                                continue;
                            }
                        }
                        else if (arg[0] == '-') {
                            if (length > 1) {
                                if (arg[1] == '-') {
                                    if (length > 2) {
                                        /*
                                            Argument full name
                                        */
                                        argName = arg + 2;
                                        auto item = this->accu.argsByName.find(argName);
                                        if (item == this->accu.argsByName.end()) {
                                            throw std::runtime_error("Invalid argument: --" + argName);
                                        }

                                        if (item->second.GetArgClass() == Helper::ArgumentClass::Option) {
                                            /*
                                                Option
                                            */
                                            this->accu.optionNames.insert(argName);
                                            continue;
                                        }
                                        else {
                                            /*
                                                Argument
                                            */
                                            expectArgValue = true;
                                            lastArg = item->second;
                                            continue;
                                        }
                                    }
                                    else {
                                        /*
                                            Two dashes only.
                                        */
                                        throw std::runtime_error("Syntax error.");
                                    }
                                }
                                else {
                                    /*
                                        One or more single-letter name
                                        after a dash.
                                    */
                                    bool foundArgument = false;

                                    for(int i = 1; i < length; i++) {
                                        auto item = this->accu.argsByLetter.find(arg[i]);
                                        if (item == this->accu.argsByLetter.end()) {
                                            throw std::runtime_error("Invalid argument letter: '"
                                                + std::string(1, arg[i]) + "'.");
                                        }

                                        if (item->second.GetArgClass() == Helper::ArgumentClass::Option) {
                                            /*
                                                Option
                                            */
                                            this->accu.optionNames.insert({item->second.GetName()});
                                        } else {
                                            /*
                                                Argument (max 1)
                                            */
                                            if (foundArgument) {
                                                throw std::runtime_error("When multiple options are provided after a single dash, "
                                                    "only one of them can be an argument. (Because each argument would require "
                                                    "a separate parameter value.) Please separate the superfluous arguments.");
                                            }

                                            lastArg = item->second;
                                            foundArgument = true;
                                            expectArgValue = true;
                                        }
                                    }

                                    continue;
                                }
                            }
                            else {
                                /*
                                    A single dash. Handle it as an operand
                                    since no argument value is expected now.
                                */
                                this->AddOperand(arg);
                            }
                        }
                        else {
                            /*
                                Operand
                            */
                            this->AddOperand(arg);
                        }
                    } catch (const std::runtime_error &err) {
                        for(int j = i + 1; j < this->argc; j++) {
                            this->Trim(&(this->argv[j]));
                            char *arg = this->argv[j];

                            if (line.tellp() > 0) {
                                line << " " << arg;
                            }
                            else {
                                line << arg;
                            }
                        }

                        this->ThrowError(err.what(), line.str(), position);
                    }
                }

                return Arguments(this->accu);
            }

            std::string GenerateDoc() {
                std::stringstream buff;

                // TODO

                return buff.str();
            }
    };
}