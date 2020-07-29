# Input / Output

- The Input object holds information about the request such option and argument values. You may need to this information when coding a hook implementation. You don't need this object in your command callback method since these values are passed as parameters.
- The Output object is rarely needed. Instead, return an object that gets formatted via the Output Formatter system. If you want to send additional output, use the io system (see below).

## The io() system 
- If you need to ask the user a question, or print non-object content, use the io() system. 
- A command callback gets access via `$this->io()`.
- The main methods for gathering user input are `$this->io()->choice()` and `$this->io()->confirm()`.
- You may use any of the methods described in the [Symfony Style docs](https://symfony.com/doc/current/console/style.html).


