<?php
/**
 * Convert old entities to utf-8
 */

namespace AppZz\Http\Kinopoisk\Helpers;

class EntitiesDecode {

	private static function chr_utf8($code)
	{
	    if ($code < 0) return false;
	    elseif ($code < 128) return chr($code);
	    elseif ($code < 160) {// Remove Windows Illegals Cars
	        if ($code==128) $code=8364;
	        elseif ($code==129) $code=160; // not affected
	        elseif ($code==130) $code=8218;
	        elseif ($code==131) $code=402;
	        elseif ($code==132) $code=8222;
	        elseif ($code==133) $code=8230;
	        elseif ($code==134) $code=8224;
	        elseif ($code==135) $code=8225;
	        elseif ($code==136) $code=710;
	        elseif ($code==137) $code=8240;
	        elseif ($code==138) $code=352;
	        elseif ($code==139) $code=8249;
	        elseif ($code==140) $code=338;
	        elseif ($code==141) $code=160; // not affected
	        elseif ($code==142) $code=381;
	        elseif ($code==143) $code=160; // not affected
	        elseif ($code==144) $code=160; // not affected
	        elseif ($code==145) $code=8216;
	        elseif ($code==146) $code=8217;
	        elseif ($code==147) $code=8220;
	        elseif ($code==148) $code=8221;
	        elseif ($code==149) $code=8226;
	        elseif ($code==150) $code=8211;
	        elseif ($code==151) $code=8212;
	        elseif ($code==152) $code=732;
	        elseif ($code==153) $code=8482;
	        elseif ($code==154) $code=353;
	        elseif ($code==155) $code=8250;
	        elseif ($code==156) $code=339;
	        elseif ($code==157) $code=160; // not affected
	        elseif ($code==158) $code=382;
	        elseif ($code==159) $code=376;
	    }
	    if ($code < 2048) return chr(192 | ($code >> 6)) . chr(128 | ($code & 63));
	    elseif ($code < 65536) return chr(224 | ($code >> 12)) . chr(128 | (($code >> 6) & 63)) . chr(128 | ($code & 63));
	    else return chr(240 | ($code >> 18)) . chr(128 | (($code >> 12) & 63)) . chr(128 | (($code >> 6) & 63)) . chr(128 | ($code & 63));
	}

    // Callback for preg_replace_callback('~&(#(x?))?([^;]+);~', 'html_entity_replace', $str);
    public static function html_entity_replace ($matches)
    {
        if ($matches[2])
        	return EntitiesDecode::chr_utf8(hexdec($matches[3]));
		elseif ($matches[1])
			return EntitiesDecode::chr_utf8($matches[3]);
        switch ($matches[3]) {
            case "nbsp": return EntitiesDecode::chr_utf8(160);
            case "iexcl": return EntitiesDecode::chr_utf8(161);
            case "cent": return EntitiesDecode::chr_utf8(162);
            case "pound": return EntitiesDecode::chr_utf8(163);
            case "curren": return EntitiesDecode::chr_utf8(164);
            case "yen": return EntitiesDecode::chr_utf8(165);
            //... etc with all named HTML entities
        }
        return false;
    }

    // because of the html_entity_decode() bug with UTF-8
    public static function convert ($string)
    {
        $string = preg_replace_callback('~&(#(x?))?([^;]+);~', array('\AppZz\Http\Kinopoisk\Helpers', 'html_entity_replace'), $string);
        return $string;
    }
}
