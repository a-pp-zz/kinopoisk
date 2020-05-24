<?php
namespace AppZz\Http\TMDB\Vendors;
use \AppZz\Http\CurlClient;
use \AppZz\Helpers\Arr;

/**
 * XML Rating
 */

class Rating extends \AppZz\Http\TMDB\DB {

	const BASE_URL    = 'https://rating.kinopoisk.ru/%d.xml';

	public function __construct () 
	{
		$this->_agent = 'random';
	}

	public function get_rating ($id)
	{
		$url = sprintf (self::BASE_URL, $id);
		$data = $this->_get_page ($url);
		
		if (empty($data) OR ! is_array($data)) {
			$this->_error('Не удалось получить рейтинг', 1000);
		}

		$ret = new \stdClass;
		$ret->kp = Arr::get($data, 'kp_rating', 0);
		$ret->imdb = Arr::get($data, 'imdb_rating', 0);

		return $ret;
	}

	private function _get_page ($url)
	{
		$request = CurlClient::get($url);
		$request->agent($this->_agent);

		if ($this->_proxy) {
            $request->proxy ($this->_proxy);
            $request->accept('json');
		} else {
			$request->accept('html', 'gzip', 'ru-RU');
		}
		
		$request->cookie_file('/tmp/kinopoisk.ru.txt');
		$response = $request->send();
		
		if ($response !== 200) {
			$this->_error ('Ошибка получения страницы с XML', $response);
		}
		
		$body = $request->get_body();

		if (empty($body)) {
			$this->_error ('Пустой исходный код страницы', 1001);	
		}

		return $body;
	}

	private function _error ($message, $code = 0)
	{
		throw new \Exception ($message, $code);
	}
}