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
			[
				"http://www.padi.cat:8080/wayback/20140404212712/http://example.com",
				"http://www.padi.cat/wayback/20140404212712/http://example.com"
			],
			// resolveWebarchiveUK
			[
				//"https://www.webarchive.org.uk/wayback/archive/20151128210021mp_/http://newsroom.herefordshire.gov.uk/2006/november/new%2Dsculpture%2Dto%2Dbe%2Dhanded%2Dover.aspx"
			],
			// resolveEuropa
			[
				//"http://collection.europarchive.org/nli/20141013204117/http://www.defense.gov/"
			],
			// resolveUKWebArchive
			[
				"http://www.webarchive.org.uk/wayback/archive/20100602000217/www.westsussex.gov.uk/ccm/navigation/your-council/election"
			],
			// resolveMemento
			[
				//"https://timetravel.mementoweb.org/memento/2010/http://www.muslimdirectory.co.uk/displayresults.php?PHPSESSID=f0fb8b41d8758983e7d43cddb556b9df&businesstype=1&orgtype=&country=UK&city=Cardiff"
			],
			// resolveYorkU
			[
				"https://digital.library.yorku.ca/wayback/20160129214328/http://en.cijnews.com/?p%3D10033"
			],
			// resolveArchiveIt
			[
				"http://wayback.archive-it.org/all/20130420084626/http://example.com"
			],
			// resolveArquivo
			[
				"http://arquivo.pt/wayback/19980205082901/http://www.caleida.pt/saramago/"
			],
			// resolveLoc
			[
				"http://webarchive.loc.gov/all/20160110110238/https://www.whitehouse.gov/"
			],
			// resolveWebharvest
			[
				"http://webharvest.gov/peth04/20041022004143/http://www.ftc.gov/os/statutes/textile/alerts/dryclean"
			],
			// resolveBibalex
			[
				"http://web.archive.bibalex.org/web/20051231070651/http://www.heimskringla.no/original/heimskringla/ynglingasaga.php",
				"https://web.petabox.bibalex.org/web/20060521125008/http://developmentgap.org/rmalenvi.html"
			],
			// resolveCollectionsCanada
			[
				//"http://www.collectionscanada.gc.ca/webarchives/20061104084225/http://broadband.gc.ca/maps/province.html?prov=48"
			],
			// resolveVeebiarhiiv
			[
				"http://veebiarhiiv.digar.ee/a/20131014091520/http://rakvere.kovtp.ee/en_GB/twin-cities"
			],
			// resolveVefsafn
			[
				"http://wayback.vefsafn.is/wayback/20110318105639/http://www.twitter.com/yagirldwoods"
			],
			// resolveProni
			[
				"http://webarchive.proni.gov.uk/20100218151844/http://www.berr.gov.uk/"
			],
			// resolveSpletni
			[],
			// resolveStanford
			[
				"https://sul-swap-prod.stanford.edu/19940102000000/http://slacvm.slac.stanford.edu/FIND/slac.html"
			],
			// resolveNationalArchives
			[
				"http://webarchive.nationalarchives.gov.uk/20110311030350/http://www.abilityvability.co.uk/"
			],
			// resolveParliamentUK
			[
				"http://webarchive.parliament.uk/20160204060058/http://www.parliament.uk/about/living-heritage/building/palace/big-ben/"
			],
			// resolveWAS
			[
				"https://eresources.nlb.gov.sg/webarchives/wayback/20160425174854/https://www.lta.gov.sg/apps/news/page.aspx?c=2&id=2dzk9l67sx9j40a1rhgdw3hvhrnxgq3zh34l77r37dj4w72jf1"
			],
			// resolveLAC
			[],
			// resolveWebRecorder
			[],
			// resolveWayback
			[
				"http://www.web.archive.org/20050327004900/http://nytimes.com/",
				"https://web.archive.org/web/20050327004900/http://nytimes.com/",
				"http://web.waybackmachine.org/20050327004900/http://nytimes.com/"
			],
			// resolveArchiveIs
			[
				"https://archive.is/20130505123206/http://www.wakefieldexpress.co.uk/community/announcements/in-memoriam/obituary-1-2735844"
			],
			// resolveWebCite
			[
				"http://www.webcitation.org/66lmEkpE8?url=http://www.ariacharts.com.au/pages/charts_display_album.asp?chart%3D1G50"
			],
			// resolvePermaCC
			[
				//"http://perma.cc/F9NT-22AK",
				//"https://perma-archives.org/warc/F9NT-22AK/http://www.goduke.com/ViewArticle.dbml?SPSID=25943&SPID=2027&DB_LANG=C&DB_OEM_ID=4200&ATCLID=152476"
			],
			// resolveGoogle
			[
				"http://webcache.googleusercontent.com/search?q=cache:http://www.gapan.org/ruth-documents/Masters%2520Medal%2520%2520Press%2520Release.pdf"
			],
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
			[
				//"http://archive.wikiwix.com/cache/20180329074145/http://www.linterweb.fr",
				//"http://archive.wikiwix.com/cache/?url=http://www.linterweb.fr"
			],
			// resolveFreezepage
			[
				//"http://www.freezepage.com/1338238555ICJBKARMZN",
				//"http://www.freezepage.com/1343081512QUPLJKJOYU?url=http://www.telegraph.co.uk/"
			]
		];

		$testResults = [
			// resolveCatalonianArchive
			[
				[
					"archive_url" => "http://padi.cat:8080/wayback/20140404212712/http://example.com",
					"url" => "http://example.com/",
					"archive_time" => 1396646832,
					"archive_host" => "catalonianarchive",
					"force" => false,
					"convert_archive_url" => true
				],
				[
					"archive_url" => "http://padi.cat:8080/wayback/20140404212712/http://example.com",
					"url" => "http://example.com/",
					"archive_time" => 1396646832,
					"archive_host" => "catalonianarchive",
					"force" => false,
					"convert_archive_url" => true
				]
			],
			// resolveWebarchiveUK
			[
				//[
				//	"archive_url" => "https://timetravel.mementoweb.org/memento/2010/http://www.muslimdirectory.co.uk/displayresults.php?PHPSESSID=f0fb8b41d8758983e7d43cddb556b9df&businesstype=1&orgtype=&country=UK&city=Cardiff",
				//	"url" => "http://www.muslimdirectory.co.uk/displayresults.php?PHPSESSID=f0fb8b41d8758983e7d43cddb556b9df&businesstype=1&orgtype=&country=UK&city=Cardiff",
				//	"archive_time" => 1612469400,
				//	"archive_host" => "memento",
				//	"force" => false
				//]
			],
			// resolveEuropa
			[
				//[
				//	"archive_url" => "https://wayback.archive-it.org/10702/1",
				//	"url" => "http://www.defense.gov/",
				//	"archive_time" => 1413232877,
				//	"archive_host" => "archiveit",
				//	"force" => false,
				//	"convert_archive_url" => true
				//]
			],
			// resolveUKWebArchive
			[
				[
					"archive_url" => "https://www.webarchive.org.uk/wayback/archive/20100602000217/www.westsussex.gov.uk/ccm/navigation/your-council/election",
					"url" => "http://www.westsussex.gov.uk/ccm/navigation/your-council/election",
					"archive_time" => 1275436937,
					"archive_host" => "ukwebarchive",
					"force" => false,
					"convert_archive_url" => true
				]
			],
			// resolveMemento
			[
				//[
				//	"archive_url" => "https://timetravel.mementoweb.org/memento/2010/http://www.muslimdirectory.co.uk/displayresults.php?PHPSESSID=f0fb8b41d8758983e7d43cddb556b9df&businesstype=1&orgtype=&country=UK&city=Cardiff",
				//	"url" => "http://www.muslimdirectory.co.uk/displayresults.php?PHPSESSID=f0fb8b41d8758983e7d43cddb556b9df&businesstype=1&orgtype=&country=UK&city=Cardiff",
				//	"archive_time" => 1612383000,
				//	"archive_host" => "memento",
				//	"force" => false
				//]
			],
			// resolveYorkU
			[
				[
					"archive_url" => "https://digital.library.yorku.ca/wayback/20160129214328/http://en.cijnews.com/?p%3D10033",
					"url" => "http://en.cijnews.com/?p%3D10033",
					"archive_time" => 1454103808,
					"archive_host" => "yorku",
					"force" => false
				]
			],
			// resolveArchiveIt
			[],
			// resolveArquivo
			[
				[
					"archive_url" => "http://arquivo.pt/wayback/19980205082901/http://www.caleida.pt/saramago/",
					"url" => "http://www.caleida.pt/saramago/",
					"archive_time" => 886667341,
					"archive_host" => "arquivo",
					"force" => false
				]
			],
			// resolveLoc
			[
				[
					"archive_url" => "http://webarchive.loc.gov/all/20160110110238/https://www.whitehouse.gov/",
					"url" => "https://www.whitehouse.gov/",
					"archive_time" => 1452423758,
					"archive_host" => "loc",
					"force" => false
				]
			],
			// resolveWebharvest
			[
				[
					"archive_url" => "https://www.webharvest.gov/peth04/20041022004143/http://www.ftc.gov/os/statutes/textile/alerts/dryclean",
					"url" => "http://www.ftc.gov/os/statutes/textile/alerts/dryclean",
					"archive_time" => 1098405703,
					"archive_host" => "warbharvest",
					"force" => false,
					"convert_archive_url" => true
				]
			],
			// resolveBibalex
			[
				[
					"archive_url" => "http://web.archive.bibalex.org/web/20051231070651/http://www.heimskringla.no/original/heimskringla/ynglingasaga.php",
					"url" => "http://www.heimskringla.no/original/heimskringla/ynglingasaga.php",
					"archive_time" => 1136012811,
					"archive_host" => "bibalex",
					"force" => false
				],
				[
					"archive_url" => "http://web.archive.bibalex.org/web/20060521125008/http://developmentgap.org/rmalenvi.html",
					"url" => "http://developmentgap.org/rmalenvi.html",
					"archive_time" => 1148215808,
					"archive_host" => "bibalex",
    				"force" => false,
    				"convert_archive_url" => true
				]
			],
			// resolveCollectionsCanada
			[],
			// resolveVeebiarhiiv
			[
				[
					"archive_url" => "http://veebiarhiiv.digar.ee/a/20131014091520/http://rakvere.kovtp.ee/en_GB/twin-cities",
					"url" => "http://rakvere.kovtp.ee/en_GB/twin-cities",
					"archive_time" => 1381742120,
					"archive_host" => "veebiarhiiv",
					"force" => false
				]
			],
			// resolveVefsafn
			[
				[
					"archive_url" => "http://wayback.vefsafn.is/wayback/20110318105639/http://www.twitter.com/yagirldwoods",
					"url" => "http://www.twitter.com/yagirldwoods",
					"archive_time" => 1300445799,
					"archive_host" => "vefsafn",
					"force" => false
				]
			],
			// resolveProni
			[
				[
					"archive_url" => "https://wayback.archive-it.org/11112/1",
					"url" => "http://www.berr.gov.uk/",
					"archive_time" => 1266506324,
					"archive_host" => "archiveit",
					"force" => false,
					"convert_archive_url" => true
				]
			],
			// resolveSpletni
			[],
			// resolveStanford
			[
				[
					"archive_url" => "https://swap.stanford.edu/19940102000000/http://slacvm.slac.stanford.edu/FIND/slac.html",
					"url" => "http://slacvm.slac.stanford.edu/FIND/slac.html",
					"archive_time" => 757468800,
					"archive_host" => "stanford",
					"force" => false,
					"convert_archive_url" => true
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
					"archive_url" => "http://webarchive.parliament.uk/20160204060058/http://www.parliament.uk/about/living-heritage/building/palace/big-ben/",
					"url" => "http://www.parliament.uk/about/living-heritage/building/palace/big-ben/",
					"archive_time" => 1454565658,
					"archive_host" => "parliamentuk",
					"force" => false
				]
			],
			// resolveWAS
			[
				[
					"archive_url" => "http://eresources.nlb.gov.sg/webarchives/wayback/20160425174854/https://www.lta.gov.sg/apps/news/page.aspx?c=2&id=2dzk9l67sx9j40a1rhgdw3hvhrnxgq3zh34l77r37dj4w72jf1",
					"url" => "https://www.lta.gov.sg/apps/news/page.aspx?c=2&id=2dzk9l67sx9j40a1rhgdw3hvhrnxgq3zh34l77r37dj4w72jf1",
					"archive_time" => 1461606534,
					"archive_host" => "was",
					"force" => false,
					"convert_archive_url" => true
				]
			],
			// resolveLAC
			[],
			// resolveWebRecorder
			[],
			// resolveWayback
			[
				[
					"archive_url" => "https://web.archive.org/web/20050327004900/http://nytimes.com/",
					"url" => "http://nytimes.com/",
					"archive_time" => 1111884540,
					"archive_host" => "wayback",
					"convert_archive_url" => true
				],
				[
					"archive_url" => "https://web.archive.org/web/20050327004900/http://nytimes.com/",
					"url" => "http://nytimes.com/",
					"archive_time" => 1111884540,
					"archive_host" => "wayback"
				],
				[
					"archive_url" => "https://web.archive.org/web/20050327004900/http://nytimes.com/",
					"url" => "http://nytimes.com/",
					"archive_time" => 1111884540,
					"archive_host" => "wayback",
					"convert_archive_url" => true
				]
			],
			// resolveArchiveIs
			[
				[
					"archive_time" => 1367757126,
					"url" => "http://www.wakefieldexpress.co.uk/community/announcements/in-memoriam/obituary-1-2735844",
					"archive_url" => "https://archive.is/20130505123206/http://www.wakefieldexpress.co.uk/community/announcements/in-memoriam/obituary-1-2735844",
					"archive_host" => "archiveis"
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
			[],
			// resolveGoogle
			[
				[
					"archive_url" => "http://webcache.googleusercontent.com/search?q=cache:http://www.gapan.org/ruth-documents/Masters%2520Medal%2520%2520Press%2520Release.pdf",
					"url" => "http://www.gapan.org/ruth-documents/Masters%2520Medal%2520%2520Press%2520Release.pdf",
					"archive_time" => 'x',
					"archive_host" => "google",
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
					"archive_url" => "http://archive.wikiwix.com/cache/20180329074145/http://www.linterweb.fr",
					"url" => "http://www.linterweb.fr/",
					"archive_time" => 1522309305,
					"archive_host" => "wikiwix",
				]
			],
			// resolveFreezepage
			[]
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
