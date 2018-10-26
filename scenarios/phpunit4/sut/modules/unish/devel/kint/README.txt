WHAT IS IT?
-----------
Kint for PHP is a tool designed to present your debugging data in the absolutely
best way possible.

In other words, it's var_dump() and debug_backtrace() on steroids. Easy to use,
but powerful and customizable. An essential addition to your development
toolbox.

USAGE
-----
This module allows to use these aliases:
    kint($data1, $data2, $data3, ...);
    ksm($data1, $data2, $data3, ...)
    kint_trace();

But to get the most out of Kint, you will want to use directly the Kint class:
    kint_require();
    Kint::dump($data);

Learn more about Kint: http://raveren.github.io/kint/


The Kint class function dd() will not work as expected, because this alias
is already defined in devel.module for other purposes.

CONTACTS
--------
Module author:
    Alexander Danilenko
    danilenko.dn@gmail.com
    https://drupal.org/user/1072104

Kint author:
    Rokas Šleinius a.k.a. Raveren
    raveren@gmail.com
    https://github.com/raveren