<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if ( getenv( 'IABOT_APP_DIRECTORY' ) ) {
	define( 'IABOT_APP_DIRECTORY', getenv( 'IABOT_APP_DIRECTORY' ) );
} else {
	define( 'IABOT_APP_DIRECTORY', __DIR__ . '/../../src/' );
}

class UrlResolverTests extends TestCase
{
	public function testUrlResolvers(): void
	{
		require IABOT_APP_DIRECTORY . '/Core/init.php';

		$functionsToTest = [
			'UrlResolver::resolveCatalonianArchive',
			'UrlResolver::resolveWebarchiveUK',
			'UrlResolver::resolveEuropa',
			'UrlResolver::resolveUKWebArchive',
			'UrlResolver::resolveMemento',
			'UrlResolver::resolveYorkU',
			'UrlResolver::resolveArchiveIt',
			'UrlResolver::resolveArquivo',
			'UrlResolver::resolveLoc',
			'UrlResolver::resolveWebharvest',
			'UrlResolver::resolveBibalex',
			'UrlResolver::resolveCollectionsCanada',
			'UrlResolver::resolveVeebiarhiiv',
			'UrlResolver::resolveVefsafn',
			'UrlResolver::resolveProni',
			'UrlResolver::resolveSpletni',
			'UrlResolver::resolveStanford',
			'UrlResolver::resolveNationalArchives',
			'UrlResolver::resolveParliamentUK',
			'UrlResolver::resolveWAS',
			'UrlResolver::resolveLAC',
			'UrlResolver::resolveWebRecorder',
			'UrlResolver::resolveWayback',
			'UrlResolver::resolveArchiveIs',
			'UrlResolver::resolveWebCite',
			'UrlResolver::resolvePermaCC',
			'UrlResolver::resolveGoogle',
			'UrlResolver::resolveNLA',
			'UrlResolver::resolveWikiwix',
			'UrlResolver::resolveFreezepage'
		];

		$testCases = [
			// resolveCatalonianArchive
			[],
			// resolveWebarchiveUK
			[],
			// resolveEuropa
			[],
			// resolveUKWebArchive
			[],
			// resolveMemento
			[],
			// resolveYorkU
			[],
			// resolveArchiveIt
			[],
			// resolveArquivo
			[],
			// resolveLoc
			[],
			// resolveWebharvest
			[],
			// resolveBibalex
			[],
			// resolveCollectionsCanada
			[],
			// resolveVeebiarhiiv
			[],
			// resolveVefsafn
			[],
			// resolveProni
			[],
			// resolveSpletni
			[],
			// resolveStanford
			[],
			// resolveNationalArchives
			[
				"http://webarchive.nationalarchives.gov.uk/20110311030350/http://www.abilityvability.co.uk/"
			],
			// resolveParliamentUK
			[],
			// resolveWAS
			[],
			// resolveLAC
			[],
			// resolveWebRecorder
			[],
			// resolveWayback
			[],
			// resolveArchiveIs
			[],
			// resolveWebCite
			[
				"http://www.webcitation.org/66lmEkpE8?url=http://www.ariacharts.com.au/pages/charts_display_album.asp?chart%3D1G50"
			],
			// resolvePermaCC
			[],
			// resolveGoogle
			[],
			// resolveNLA
			[
				"http://pandora.nla.gov.au/pan/14231/20120727-0512/www.howlspace.com.au/en2/inxs/inxs.htm",
				"http://pandora.nla.gov.au/pan/128344/20110810-1451/www.theaureview.com/guide/festivals/bam-festival-2010-ivorys-rock-qld.html",
				"http://pandora.nla.gov.au/nph-wb/20010328130000/http://www.howlspace.com.au/en2/arenatina/arenatina.htm",
				"http://pandora.nla.gov.au/nph-arch/2000/S2000-Dec-5/http://www.paralympic.org.au/athletes/athleteprofile60da.html",
				"http://webarchive.nla.gov.au/gov/20120326012340/http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability/",
				"http://content.webarchive.nla.gov.au/gov/wayback/20120326012340/http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability"
			],
			// resolveWikiwix
			[],
			// resolveFreezepage
			[]
		];

		$testResults = [
			// resolveCatalonianArchive
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWebarchiveUK
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveEuropa
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveUKWebArchive
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveMemento
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveYorkU
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveArchiveIt
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveArquivo
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveLoc
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWebharvest
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveBibalex
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveCollectionsCanada
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveVeebiarhiiv
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveVefsafn
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveProni
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveSpletni
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveStanford
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveNationalArchives
			[
				[
					"archive_url" => "http://webarchive.nationalarchives.gov.uk/20110311030350/http://www.abilityvability.co.uk/",
					"url" => "http://www.abilityvability.co.uk/",
					"archive_time" => 1299812630,
					"archive_host" => "nationalarchives",
					"force" => false
				]
			],
			// resolveParliamentUK
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWAS
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveLAC
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWebRecorder
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWayback
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveArchiveIs
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveWebCite
			[
				[
					"archive_time" => "1333884124",
					"url" => "http://www.ariacharts.com.au/pages/charts_display_album.asp?chart=1G50",
					"archive_url" => "https://www.webcitation.org/66lmEkpE8?url=http://www.ariacharts.com.au/pages/charts_display_album.asp?chart=1G50",
					"archive_host" => "webcite",
					"convert_archive_url" => true
				]
			],
			// resolvePermaCC
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveGoogle
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveNLA
			[
				[
					"archive_url" => "http://pandora.nla.gov.au/pan/14231/20120727-0512/www.howlspace.com.au/en2/inxs/inxs.htm",
					"url" => "http://www.howlspace.com.au/en2/inxs/inxs.htm",
					"archive_time" => 1343365920,
					"archive_host" => "nla",
				],
				[
					"archive_url" => "http://pandora.nla.gov.au/pan/128344/20110810-1451/www.theaureview.com/guide/festivals/bam-festival-2010-ivorys-rock-qld.html",
					"url" => "http://www.theaureview.com/guide/festivals/bam-festival-2010-ivorys-rock-qld.html",
					"archive_time" => 1312987860,
					"archive_host" => "nla",
				],
				[
					"archive_url" => "http://pandora.nla.gov.au/nph-wb/20010328130000/http://www.howlspace.com.au/en2/arenatina/arenatina.htm",
					"url" => "http://www.howlspace.com.au/en2/arenatina/arenatina.htm",
					"archive_time" => 985784400,
					"archive_host" => "nla",
				],
				[
					"archive_url" => "http://pandora.nla.gov.au/nph-arch/2000/S2000-Dec-5/http://www.paralympic.org.au/athletes/athleteprofile60da.html",
					"url" => "http://www.paralympic.org.au/athletes/athleteprofile60da.html",
					"archive_time" => 975974400,
					"archive_host" => "nla",
				],
				[
					"archive_url" => "http://webarchive.nla.gov.au/gov/20120326012340/http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability/",
					"url" => "http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability/",
					"archive_time" => 1332725020,
					"archive_host" => "nla",
				],
				[
					"archive_url" => "http://content.webarchive.nla.gov.au/gov/wayback/20120326012340/http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability",
					"url" => "http://news.defence.gov.au/2011/09/09/army-airborne-insertion-capability",
					"archive_time" => 1332725020,
					"archive_host" => "nla",
				],
			],
			// resolveWikiwix
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			],
			// resolveFreezepage
			[
				[
					"archive_url" => "",
					"url" => "",
					"archive_time" => 0,
					"archive_host" => "",
				]
			]
		];

		for ( $i = 0; $i < count($testCases); $i++ ) {
			if ( count($testCases[$i]) > 0 && count($testResults[$i]) > 0 ) {
				for ( $j = 0; $j < count($testCases[$i]); $j++ ) {
					$testCase = call_user_func($functionsToTest[$i], $testCases[$i][$j]);
					$this->assertSame($testCase, $testResults[$i][$j]);
				}
			}
		}
	}
}
