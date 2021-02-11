<?php
namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Http\CurlClient;
use \AppZz\Http\Helpers\FastImage;

/**
 * Unofficial API Parser
 * @link https://kinopoiskapiunofficial.tech
 * @package Kinopoisk/Api
 * @author CoolSwitcher
 * @version 1.0.0
 */
class Api extends Kinopoisk {

	const API_HOST = 'https://kinopoiskapiunofficial.tech';
	const API_FILMS_ENDPOINT = '/api/v%s/films/%d';
	const API_FRAMES_ENDPOINT = '/api/v%s/films/%d/frames';

	protected $_fields = array (
		'kp_id'       => 'ID КП',
		'name'        => 'Название',
		'original'    => 'Оригинальное название',
		'description' => 'Аннотация',
		'year'        => 'Год',
		'genre'       => 'Жанр',
		'country'     => 'Страна',
		'duration'    => 'Хронометраж',
		'rating_kp'   => 'Рейтинг КП',
		'rating_imdb' => 'Рейтинг IMDB',
		'actors'      => 'Актеры',
		'director'    => 'Режиссер',
		'producer'    => 'Продюсер',
		'poster'      => 'Постер',
		'picshots'    => 'Кадры'
	);

	protected $_agent   = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_0 like Mac OS X) AppleWebKit/602.1.38 (KHTML, like Gecko) Version/10.0 Mobile/14A5297c Safari/602.1';

	protected $_titles  = false;
	protected $_version = '2.1';
	protected $_api_key;
	protected $_result;

	private $_data;
	private $_frames;

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    	$this->_set_url ();
    }

    public function destruct ()
    {
    	$this->_result = null;
    }

    public function fields (array $fields = array ())
    {
    	$this->_fields = $fields;
    	return $this;
    }

    public function titles ($titles = true)
    {
    	$this->_titles = (bool)$titles;
    	return $this;
    }

    public function version ($version = '2.1')
    {
    	$this->_version = $version;
    	return $this;
    }

    public function api_key ($key = '')
    {
    	$this->_api_key = $key;
    	return $this;
    }

	public function get_data ()
	{
		$this->_data = $this->_request (Api::API_FILMS_ENDPOINT);
		return ! empty ($this->_data);
	}

	public function get_frames ()
	{
		$this->_frames = $this->_request (Api::API_FRAMES_ENDPOINT);
		return ! empty ($this->_frames);
	}

	public function get ()
	{
		$this->_populate();
		return $this->_result;
	}

	private function _implode_arrays ($array = array (), $sep = ', ')
	{
		foreach ($array as $key=>&$value) {
			if (is_array($value)) {
				$value = implode ($sep, $value);
			}
		}

		return implode ($sep, $array);
	}

	private function _populate ()
	{
		$this->_result = array ();

		if (is_object($this->_data)) {
			$this->_result = (array)json_decode(json_encode($this->_data), true);
			$this->_result = Arr::get ($this->_result, 'data');
			unset ($this->_data);
		}

		if (is_object($this->_frames)) {
			$this->_frames = (array)json_decode(json_encode($this->_frames), true);
			$this->_frames = Arr::get ($this->_frames, 'frames');

			if ( ! empty ($this->_frames)) {
				$this->_result['frames'] = $this->_frames;
				unset ($this->_frames);
			}
		}

		if ( ! empty ($this->_result)) {

			$check_str = '';

			foreach ($this->_result as $value) {
				if ( ! is_array($value)) {
					$check_str .= $value;
				}
			}

			if (empty ($check_str)) {
				$this->_result = false;
				return $this;
			}
		} else {
			$this->_result = false;
			return $this;
		}

		foreach ($this->_result as $key=>&$value) {

			switch ($key) {
				case 'filmId':
					$pop_key = 'kp_id';
				break;
				case 'countries':
					$value = $this->_implode_arrays($value, ', ');
					$pop_key = 'country';
				break;
				case 'genres':
					$value = $this->_implode_arrays($value, ', ');
					$pop_key = 'genre';
				break;

				case 'filmLength':
					$pop_key = 'duration';
				break;

				case 'nameRu':
					$pop_key = 'name';
				break;

				case 'nameEn':
					$pop_key = 'original';
				break;

				case 'description':
				case 'year':
					$pop_key = $key;
				break;

				case 'posterUrl':
					$pop_key = 'poster';
					$fi = new FastImage ($value);
					$size = $fi->get_size ();

					if (is_array($size) AND count ($size) === 2) {
						list ($width, $height) = $size;
					} else {
						$width = $height = 0;
					}

					$value = array ('image'=>$value, 'width'=>$width, 'height'=>$height);
					unset($fi);
				break;

				case 'frames':
					$pop_key = 'picshots';
					foreach ($value as &$image) {
						$image_url = Arr::get ($image, 'image');
						$preview_url = Arr::get ($image, 'preview');

						if ( ! empty ($image_url)) {
							$fi = new FastImage ($image['image']);
							$size = $fi->get_size ();

							if (is_array($size) AND count ($size) === 2) {
								list ($width, $height) = $size;
							} else {
								$width = $height = 0;
							}

							$image = array ('image'=>$image_url, 'thumb'=>$preview_url, 'width'=>$width, 'height'=>$height);
							unset($fi);
						}
					}
				break;

				default:
					$pop_key = null;
				break;
			}

			if ($pop_key) {
				if ($this->_titles) {
					$value = array ('field'=>$pop_key, 'title'=>Arr::get($this->_fields, $pop_key, ''), 'value'=>$value);
				}
				$populated[$pop_key] = $value;
			}
		}

		if ($this->_titles) {
			$populated = array_values ($populated);
		}

		$this->_result = $populated;
		unset ($populated);
	}

    protected function _request ($url = NULL)
    {
    	$url = Api::API_HOST.sprintf ($url, $this->_version, $this->_kpid);

        $request = CurlClient::get($url);
        $request->agent($this->_agent);
        $request->timeout(10);
        $request->accept('json', 'gzip', 'ru-RU');
        $request->add_header('X-API-KEY', $this->_api_key);
        $response = $request->send();

        if ($response !== 200) {
            $this->_error ('Ошибка получения данных c Api', $response);
        }

        $body = $request->get_body();
        $headers = $request->get_headers();

        if (empty($body)) {
            $this->_error ('Пустой ответ', 1001);
        }

        return $body;
    }
}
