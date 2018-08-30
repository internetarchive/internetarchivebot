<?php
/*
	Copyright (c) 2015-2018, Maximilian Doerr
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$locales = [
	'af'      => [ 'af_ZA.UTF-8', 'Afrikaans_South Africa.1252' ],
	'als'     => [ 'de_DE.UTF-8', 'German_Germany.1252' ],
	'bar'     => [ 'de_DE.UTF-8', 'German_Germany.1252' ],
	'ar'      => [ 'ar_SA.UTF-8', 'Arabic_Saudi Arabia.1256' ],
	'be'      => [ 'be_BY.UTF-8', 'Belarusian_Belarus.1251' ],
	'bg'      => [ 'bg_BG.UTF-8', 'Bulgarian_Bulgaria.1251' ],
	'bs'      => [ 'bs_BA.UTF-8', 'Bosnian (Latin)' ],
	'ca'      => [ 'ca_ES.UTF-8', 'Catalan_Spain.1252' ],
	'cku'     => [ 'ku_TR.UTF-8', 'ku_TR.iso88599', 'Central Kurdish_Iraq' ],
	'cs'      => [ 'cs_CZ.UTF-8', 'Czech_Czech Republic.1250' ],
	'da'      => [ 'da_DK.UTF-8', 'Danish_Denmark.1252' ],
	'de'      => [ 'de_DE.UTF-8', 'German_Germany.1252' ],
	'el'      => [ 'el_GR.UTF-8', 'Greek_Greece.1253' ],
	'en'      => [ 'en_US.UTF-8', 'en.UTF-8', 'English_Australia.1252' ],
	'es'      => [ 'es_ES.UTF-8', 'Spanish_Spain.1252' ],
	'et'      => [ 'et_EE.UTF-8', 'Estonian_Estonia.1257' ],
	'eu'      => [ 'eu_ES.UTF-8', 'Basque_Spain.1252' ],
	'fa'      => [ 'fa_IR.UTF-8', 'Farsi_Iran.1256' ],
	'fi'      => [ 'fi_FI.UTF-8', 'Finnish_Finland.1252' ],
	'fil'     => [ 'ph_PH.UTF-8', 'Filipino_Philippines.1252' ],
	'fr'      => [ 'fr_FR.UTF-8', 'fr_CH.UTF-8', 'fr_BE.UTF-8', 'French_France.1252' ],
	'fr_can'  => [ 'fr_CA.UTF-8', 'French_Canada.1252' ],
	'ga'      => [ 'ga.UTF-8', 'ga_IE.UTF-8', 'Gaelic', 'Scottish Gaelic', 'Gaelic; Scottish Gaelic' ],
	'gl'      => [ 'gl_ES.UTF-8', 'Galician_Spain.1252' ],
	'gu'      => [ 'gu.UTF-8', 'gu_IN.UTF-8', 'Gujarati_India.0' ],
	'he'      => [ 'he_IL.utf8', 'Hebrew_Israel.1255' ],
	'hi'      => [ 'hi_IN.UTF-8', 'Hindi.65001' ],
	'hr'      => [ 'hr_HR.UTF-8', 'Croatian_Croatia.1250' ],
	'hu'      => [ 'hu.UTF-8', 'hu_HU.UTF-8', 'Hungarian_Hungary.1250' ],
	'id'      => [ 'id_ID.UTF-8', 'Indonesian_indonesia.1252' ],
	'is'      => [ 'is_IS.UTF-8', 'Icelandic_Iceland.1252' ],
	'it'      => [ 'it_IT.UTF-8', 'Italian_Italy.1252' ],
	'ja'      => [ 'ja_JP.UTF-8', 'Japanese_Japan.932' ],
	'ka'      => [ 'ka_GE.UTF-8', 'Georgian_Georgia.65001' ],
	'km'      => [ 'km_KH.UTF-8', 'Khmer.65001' ],
	'kn'      => [ 'kn_IN.UTF-8', 'Kannada.65001' ],
	'ko'      => [ 'ko_KR.UTF-8', 'Korean_Korea.949' ],
	'lo'      => [ 'lo_LA.UTF-8', 'Lao_Laos.UTF-8' ],
	'lt'      => [ 'lt_LT.UTF-8', 'Lithuanian_Lithuania.1257' ],
	'lv'      => [ 'lat.UTF-8', 'Latvian_Latvia.1257' ],
	'mi'      => [ 'mi_NZ.UTF-8', 'Maori.1252' ],
	'ml'      => [ 'ml_IN.UTF-8', 'Malayalam_India.x-iscii-ma' ],
	'mn'      => [ 'mn.UTF-8', 'mn_MN.UTF-8', 'Cyrillic_Mongolian.1251' ],
	'ms'      => [ 'ms_MY.UTF-8', 'Malay_malaysia.1252' ],
	'nb'      => [ 'nb_NO', 'nb_NO.iso88591', 'no_NO.UTF-8', 'Norwegian_Norway.1252' ],
	'nl'      => [ 'nl_NL.UTF-8', 'nl_AW', 'nl_AW.utf8', 'nl_BE.utf8', 'Dutch_Netherlands.1252' ],
	'nn'      => [ 'nn_NO', 'nn_NO.iso88591', 'nn_NO.UTF-8', 'no_NO.UTF-8', 'Norwegian-Nynorsk_Norway.1252' ],
	'no'      => [ 'no_NO', 'no_NO.UTF-8', 'Norwegian_Norway.1252' ],
	'pa'      => [ 'pa_IN.UTF-8', 'pa_PK.UTF-8' ],
	'pl'      => [ 'pl.UTF-8', 'pl_PL.UTF-8', 'Polish_Poland.1250' ],
	'pt'      => [ 'pt_PT.UTF-8', 'Portuguese_Portugal.1252' ],
	'pt_br'   => [ 'pt_BR.UTF-8', 'Portuguese_Brazil.1252' ],
	'ro'      => [ 'ro_RO.UTF-8', 'Romanian_Romania.1250' ],
	'ru'      => [ 'ru_RU.UTF-8', 'Russian_Russia.1251' ],
	'sk'      => [ 'sk_SK.UTF-8', 'Slovak_Slovakia.1250' ],
	'sl'      => [ 'sl_SI.UTF-8', 'Slovenian_Slovenia.1250' ],
	'sm'      => [ 'mi_NZ.UTF-8', 'Maori.1252' ],
	'so'      => [ 'so_SO.UTF-8', 'Somali_Somalia' ],
	'sr'      => [
		'sr_CS.UTF-8', 'sr_ME.UTF-8', 'sr_RS.UTF-8@latin', 'sr_RS.UTF-8', 'Bosnian(Cyrillic)', 'Serbian (Cyrillic)'
	],
	'sq'      => [ 'sq_AL.UTF-8', 'Albanian_Albania.1250', ],
	'sv'      => [ 'sv_SE.UTF-8', 'Swedish_Sweden.1252' ],
	'ta'      => [ 'ta_IN.UTF-8', 'ta_IN.UTF-8', 'ta_LK.UTF-8', 'English_Australia.1252' ],
	'th'      => [ 'th_TH.UTF-8', 'Thai_Thailand.874' ],
	'tl'      => [ 'tl.UTF-8', 'tl_PH.UTF-8' ],
	'to'      => [ 'mi_NZ.UTF-8', 'Maori.1252' ],
	'tr'      => [ 'tr_TR.UTF-8', 'Turkish_Turkey.1254' ],
	'uk'      => [ 'uk_UA.UTF-8', 'Ukrainian_Ukraine.1251' ],
	'vi'      => [ 'vi_VN.UTF-8', 'Vietnamese_Viet Nam.1258' ],
	'yue'     => [ 'zh_CN.UTF-8', 'zh_CN.gb2312', 'Chinese_China.936' ],
	'zh-hans' => [ 'zh_CN.UTF-8', 'zh_CN.gb2312', 'Chinese_China.936' ],
	'zh-hant' => [ 'zh_TW.UTF-8', 'zh_TW.big5', 'Chinese_Taiwan.950' ]
];

class IABotLocalization {
	public static function localize_bn( $timestamp, $toEN = false ) {
		$digits = [
			'0'         => "০",
			'1'         => "১",
			'2'         => "২",
			'3'         => "৩",
			'4'         => "৪",
			'5'         => "৫",
			'6'         => "৬",
			'7'         => "৭",
			'8'         => "৮",
			'9'         => "৯",
			'January'   => "জানুয়ারি",
			'February'  => "ফেব্রুয়ারি",
			'March'     => "মার্চ",
			'April'     => "এপ্রিল",
			'May'       => "মে",
			'June'      => "জুন",
			'July'      => "জুলাই",
			'August'    => "আগস্ট",
			'September' => "সেপ্টেম্বর",
			'October'   => "অক্টোবর",
			'November'  => "নভেম্বর",
			'December'  => "ডিসেম্বর"
		];

		if( $toEN === true ) {
			$digits = array_flip( $digits );
		}

		foreach( $digits as $search => $replace ) {
			$timestamp = str_ireplace( $search, $replace, $timestamp );
		}

		return $timestamp;
	}

	public static function localize_ckb( $timestamp, $toEN = false ) {
		$digits = [
			'0'         => "٠",
			'1'         => "١",
			'2'         => "٢",
			'3'         => "٣",
			'4'         => "٤",
			'5'         => "٥",
			'6'         => "٦",
			'7'         => "٧",
			'8'         => "٨",
			'9'         => "٩",
			'January'   => "کانوونی دووەم",
			'February'  => "شوبات",
			'March'     => "ئازار",
			'April'     => "نیسان",
			'May'       => "ئایار",
			'June'      => "حوزەیران",
			'July'      => "تەممووز",
			'August'    => "ئاب",
			'September' => "ئەیلوول",
			'October'   => "تشرینی یەکەم",
			'November'  => "تشرینی دووەم",
			'December'  => "کانوونی یەکەم"
		];

		if( $toEN === true ) {
			$digits = array_flip( $digits );
		}

		foreach( $digits as $search => $replace ) {
			$timestamp = str_ireplace( $search, $replace, $timestamp );
		}

		return $timestamp;
	}

	public static function localize_gl( $timestamp, $toEN = false ) {
		$digits = [
			'January'   => "xaneiro",
			'February'  => "febreiro",
			'March'     => "marzo",
			'April'     => "abril",
			'May'       => "maio",
			'June'      => "xuño",
			'July'      => "xullo",
			'August'    => "agosto",
			'September' => "setembro",
			'October'   => "outubro",
			'November'  => "novembro",
			'December'  => "decembro"
		];

		if( $toEN === true ) {
			$digits = array_flip( $digits );
		}

		foreach( $digits as $search => $replace ) {
			$timestamp = str_ireplace( $search, $replace, $timestamp );
		}

		return $timestamp;
	}

	public static function localize_lv( $timestamp, $toEN = false ) {
		$digits = [
			'January'   => "Janvāris",
			'February'  => "Februāris",
			'March'     => "Marts",
			'April'     => "Aprīlis",
			'May'       => "Maijs",
			'June'      => "Jūnijs",
			'July'      => "Jūlijs",
			'August'    => "Augusts",
			'September' => "Septembris",
			'October'   => "Oktobris",
			'November'  => "Novembris",
			'December'  => "Decembris"
		];

		if( $toEN === true ) {
			$digits = array_flip( $digits );
		}

		foreach( $digits as $search => $replace ) {
			$timestamp = str_ireplace( $search, $replace, $timestamp );
		}

		return $timestamp;
	}

	public static function localize_sr( $timestamp, $toEN = false ) {
		$digits = [
			'January'   => "Јануар",
			'February'  => "Фебруар",
			'March'     => "Март",
			'April'     => "Април",
			'May'       => "Мај",
			'June'      => "Јуни",
			'July'      => "Јули",
			'August'    => "Август",
			'September' => "Септембар",
			'October'   => "Октобар",
			'November'  => "Новембар",
			'December'  => "Децембар"
		];

		if( $toEN === true ) {
			$digits = array_flip( $digits );
		}

		foreach( $digits as $search => $replace ) {
			$timestamp = str_ireplace( $search, $replace, $timestamp );
		}

		return $timestamp;
	}

	public static function localize_gl_extend( $timestamp, $toEN = false ) {
		if( $toEN === false ) return strtolower( $timestamp );
		else return $timestamp;
	}

	public static function localize_hu_extend( $timestamp, $toEN = false ) {
		if( $toEN === false ) return strtolower( $timestamp );
		else return $timestamp;
	}

	public static function localize_it_extend( $timestamp, $toEN = false ) {
		if( $toEN === false ) return strtolower( $timestamp );
		else return $timestamp;
	}
}