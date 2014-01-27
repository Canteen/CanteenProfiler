#Canteen Profiler

Canteen Profiler is a useful tool for debugging performance and memory usage of PHP. Also, Profiler has additional options for messuring SQL query performance. [Canteen Profiler docs](http://canteen.github.io/CanteenProfiler/).

##Installation

Install is available using [Composer](http://getcomposer.org).

```bash
composer require canteen/profiler dev-master
```

Including using the Composer autoloader in your index.

```php
require 'vendor/autoload.php';
```

##Sample Usage

```php
use Canteen\Profiler\Profiler;

// Create the profiler
$profiler = new Profiler();

$profiler->start('Some Task');
// bunch of code here!
$profiler->end('Some Task');

// Render the profiler onto your page
echo $profiler->render();
```

###Rebuild Documentation

This library is auto-documented using [YUIDoc](http://yui.github.io/yuidoc/). To install YUIDoc, run `sudo npm install yuidocjs`. Also, this requires the project [CanteenTheme](http://github.com/Canteen/CanteenTheme) be checked-out along-side this repository. To rebuild the docs, run the ant task from the command-line. 

```bash
ant docs
```

##License##

Copyright (c) 2013 [Matt Karl](http://github.com/bigtimebuddy)

Released under the MIT License.