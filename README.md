# Kinopoisk Utils

**Install via composer:**

```
composer require 'appzz/kinopoisk'
```

Usage:

```
$kp = new AppZz\Http\Kinopoisk\Kinopoisk (1272854);
$parser = $kp->parser();
$rating = $kp->rating();

$data = $parser->parse();
print_r ($data);

$r = $rating->get_rating();
print_r ($data);
```
