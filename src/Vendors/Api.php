<?php
namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Http\CurlClient;
use \AppZz\Http\Helpers\FastImage;

/**
 * Unofficial API Wrapper
 * @link https://kinopoiskapiunofficial.tech
 * @package Kinopoisk/Api
 * @author CoolSwitcher
 * @version 1.0.1
 */
class Api extends Kinopoisk {

	const API_HOST = 'https://kinopoiskapiunofficial.tech';
	const API_FILMS_ENDPOINT = '/api/v%s/films/%d';
	const API_FRAMES_ENDPOINT = '/api/v%s/films/%d/frames';

	protected $_referer = '';
	protected $_content_type = 'json';
	protected $_version = '2.1';

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    }

    public function version ($version = '2.1')
    {
    	$this->_version = $version;
    	return $this;
    }

    public function api_key ($key = '')
    {
    	$this->_headers = array (
    		'X-API-KEY' => $key
    	);
    	return $this;
    }

	public function get_data ($cache = false)
	{
		$url = Api::API_HOST.sprintf (Api::API_FILMS_ENDPOINT, $this->_version, $this->_kpid);
		$this->_data = $this->_request ($url);

		if (is_object($this->_data)) {
			$this->_data = (array)json_decode(json_encode($this->_data), true);
			$this->_data = Arr::get ($this->_data, 'data');
		}

		return ! empty ($this->_data);
	}

	public function get_frames ($max = 5, $cache = false)
	{
		$url = Api::API_HOST.sprintf (Api::API_FRAMES_ENDPOINT, $this->_version, $this->_kpid);
		$this->_frames = $this->_request ($url);

		if (is_object($this->_frames)) {
			$this->_frames = (array)json_decode(json_encode($this->_frames), true);
			$this->_frames = (array)Arr::get ($this->_frames, 'frames');

			if ($max AND ! empty ($this->_frames)) {
				$this->_frames = array_slice ($this->_frames, 0, $max);
			}
		}

		return ! empty ($this->_frames);
	}

	public function get_rating ()
	{
		$this->_rating = $this->_get_rating();
		return ! empty ($this->_rating);
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

	protected function _populate ()
	{
		$this->_result = (array)$this->_data;
		$this->_frames = (array)$this->_frames;

		if ( ! empty ($this->_frames)) {
			$this->_result['frames'] = $this->_frames;
		}

		if ( ! empty ($this->_rating)) {
			$this->_result = array_merge ($this->_result, $this->_rating);
		}

		unset ($this->_data);
		unset ($this->_frames);
		unset ($this->_rating);

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
				case 'rating_kp':
				case 'rating_imdb':
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
					$value = array_splice ($value, 0, 5);
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
				$populated[$pop_key] = $value;
			}
		}

		$this->_result = $populated;
		unset ($populated);
	}
}
