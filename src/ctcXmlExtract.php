<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;

/**
 * Experimental methods for 'brute-force' extraction of an XML string 
 * and repairing the damage afterwards. 
 */

class ctcXmlExtract {

	public static function extractFragmentFromXml( $xmlStr, $boundary1, $boundary2 ) {
		$boundary1Arr = explode( "---", $boundary1, 3 );
		$boundary2Arr = explode( "---", $boundary2, 3 );
		$tag1 = $boundary1Arr[0];
		$tag2 = $boundary2Arr[0];
		$attr1 = $boundary1Arr[1];
		$attr2 = $boundary2Arr[1];
		$val1 = $boundary1Arr[2];
		$val2 = $boundary2Arr[2];

		$patternTagInternal = "[^<>\/]*";
		//$patternTagInternal = "(.*?)"; // matches too much
		$patternGreedy = "(.*)?"; // greedy
		$patternNonGreedy = "(.*?)"; //non-greedy
		
		$patternStart = "<{$tag1}(\s+)*{$patternTagInternal}(\s+)*{$attr1}=('|\"){$val1}('|\")(.*?)\/>";
		$patternEnd   = "<{$tag2}(\s+)*{$patternTagInternal}(\s+)*{$attr2}=('|\"){$val2}('|\")(.*?)\/>";
		$pattern = "~{$patternStart}{$patternNonGreedy}{$patternEnd}~msi";

		preg_match_all( $pattern, $xmlStr, $matches );

		if ( array_key_exists( 0, $matches[0] )) {
			$replacePattern = "/{$patternEnd}/";
			$res = preg_replace( $replacePattern, "", $matches[0][0], 1 );
			return $res;
		}
	}

	/**
	 * Complex method for repairing XML with dangling closing and opening tags
	 * Automatically supplies missing tags for broken XML
	 * @param string $str
	 * @return array
	 */
	public static function repairXml( $str ) {
		// Get an array of opening and closed tags with offset
		// Exclude self-closing tags. List probably not yet comprehensive - ?g
		// gn = gathering
		$pattern1 = '#<(?!lb|cb|pb|mls|milestone|gn|br|hr|link|addSpan|anchor|divGen|ptr|gap|graphic|meta|img|input\b)\b([a-zA-Z0-9-]+)(?: .*)?(?<![/|/ ])>#iU';
		preg_match_all( $pattern1, $str, $resultOpen, PREG_OFFSET_CAPTURE );
		$openingtags = $resultOpen[1];
		// Closing string </foo> or [erha[s]] </foo >, </ foo >
		$pattern2 = '#</([a-zA-Z0-9-]+)>#iU';
		preg_match_all( $pattern2, $str, $resultClosed, PREG_OFFSET_CAPTURE );
		$closingtags = $resultClosed[1];

		// Also get unique tag names
		// which will become important later on
		preg_match_all( $pattern1, $str, $resultOpenSimple );
		preg_match_all( $pattern2, $str, $resultClosedSimple );
		$mergedTagNames = array_merge( $resultOpenSimple[1], $resultClosedSimple[1] );
		$allTagNames = array_unique( $mergedTagNames ); // 'p', 'sel', ...
		// self::printArray( $allTagNames );

		// Create one big sorted array of tag items,
		// with tagname (1), offset (2) and label 'open/close' (3)
		$allTags = self::mergeAndSortTags( $openingtags, $closingtags );
		// Reuse array to produce a string like '</div><p><p></div>'
		$tagInstancesStr = self::concatAllTagInstances( $allTags );
		// The difficult part:
		// remove each tag pair until we're left with lonely/unmatched tags
		$tagInstancesLonely = self::removeAllTagPairs( $tagInstancesStr, $allTagNames );
		// self::printRawText( $tagInstancesLonely );

		// Match the lonely tags with new friends (array)
		$friendsArr = self::matchLonelyTags( $tagInstancesLonely );
		return( $friendsArr );
	}

	/**
	 * @param array $openingtags
	 * @param array $closingtags
	 * @return array
	 */
	public static function mergeAndSortTags( $openingtags, $closingtags ) {
		// Add "open" to array
		$openingtagsCount = count($openingtags);
		for ( $i=0; $i < $openingtagsCount; $i++ ) {
			$openingtags[$i][2] = "open";
		}
		// Add "close" to array
		$closingtagsCount = count($closingtags);
		for ( $i=0; $i < $closingtagsCount; $i++ ) {
			$closingtags[$i][2] = "close";
		}
		// Merge and re-sort 
		$mergedTags = array_merge( $openingtags, $closingtags );
		$sortedTags = [];
		foreach ( $mergedTags as $val ) {
			$newkey = $val[1];
			$sortedTags[$newkey] = $val;
		}
		ksort( $sortedTags );
		$allTags = [];
		foreach ( $sortedTags as $val ) {
			$allTags[] = $val;
		}
		return $allTags;
	}

	/**
	 * @param array $allTags
	 * @return string
	 */
	public static function concatAllTagInstances( array $allTags ) {
		$str = "";
		foreach ( $allTags as $k => $tag ) {
			$tagName = $tag[0];
			if ( $tag[2] == 'open' ) {
				$instance = "<{$tagName}>";
			} elseif ( $tag[2] == 'close' ) {
				$instance = "</{$tagName}>";
			}
			$str .= $instance;
		}
		return $str;
	}

	/**
	 * @param string $tagInstancesStr
	 * @param array $removeAllTagPairs
	 * @return string
	 */
	public static function removeAllTagPairs( $tagInstancesStr, $allTagNames ) {
		$str = $tagInstancesStr;
		$newStr = $prev = "";
		do {
			$prev = $str;
			$newStr = $str = self::removePairsForTags( $str, $allTagNames );
		} while ( $newStr !== $prev );
		return $str;
	}

	/**
	 * @param string $str
	 * @param array $allTagNames
	 * @return string
	 */
	public static function removePairsForTags( string $str, array $allTagNames ) {
		foreach ( $allTagNames as $tagName ) {
			$newStr = $prev = "";
			do {
				$prev = $str;
				$newStr = str_replace( "<{$tagName}></{$tagName}>", "", $str );
				$str = $newStr;
			} while ( $newStr !== $prev );
		}
		// use getFriendTags here? 
		return $str;
	}

	/**
	 * At this point we should have removed any legitimate tag from our picture
	 * @param string $str
	 * @return array
	 **/
	public static function matchLonelyTags( string $str ) {
		$exampleStr = "</span></div><div><div><p>";
		//regex pattern
		$pattern1 = '#<(?!lb\b)\b([a-zA-Z0-9]+)(?: .*)?(?<![/|/ ])>#iU';
		//$pattern1 = '#<(?\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU';
		//$pattern1 = '#<([a-z]+)>#iU';
		$pattern2 = '#</([a-zA-Z0-9]+)>#iU';

		// Get closing tags first
		preg_match_all( $pattern2, $str, $resultClosed );
		$closingtags = $resultClosed[1];
		$closingtagsCount = count($closingtags);

		// Match with opening tags in reverse order
		$matchingOpeningTags = $matchingOpeningTagsTest = "";
		for( $i=($closingtagsCount - 1); $i>=0; $i-- ) {
			$tagName = $closingtags[$i];
			$matchingOpeningTags .= "<{$tagName}>";
			$matchingOpeningTagsTest .= "($i. {$tagName})";
		}
		//self::printRawText( 'Matching opening tags at the start: ' );
		// print_r( $closingtagsCount);
		//self::printArray( $closingtags );
		//self::printRawText( $matchingOpeningTags );

		// Get opening tags
		preg_match_all( $pattern1, $str, $resultOpen );
		$openingtags = $resultOpen[1];
		$openingtagsCount = count($openingtags);
		// Match with closing tags in reverse order
		$matchingClosingTags = $matchingClosingTagsTest = "";
		for( $i=($openingtagsCount - 1); $i>=0; $i-- ) {
			$tagName = $openingtags[$i];
			$matchingClosingTags .= "</{$tagName}>";
			$matchingClosingTagsTest .= "($i. {$tagName})";
		}
		//self::printRawText( 'Matching closing tags at the end: ' );
		//print_r( count($openingtags) );
		//self::printArray( $openingtags );
		//self::printRawText( $matchingClosingTags );
		//self::printRawText( $matchingClosingTagsTest );

		$arr[0] = $matchingOpeningTags;
		$arr[1] = $matchingClosingTags;
		//self::printRawText( $arr[0] );
		//self::printRawText( $arr[1] );
		return $arr;
	}


	// Utils
	public static function printArray( $arr ) {
		print_r( "<pre>" );
		print_r( $arr);
		print_r( "</pre>" );
	}

	public static function printRawText( $str ) {
		print_r( "<pre>" );
		print_r( htmlspecialchars($str) );
		print_r( "</pre>" );
	}

	/**
	 * A deliberately broken XML fragment used for testing repairXml()
	 * Example taken from CELT (G301900)
	 * Lebor na hUidre, p. 2 of the edition
	 */
	public static function getExampleFromCELT() {
		// with closing p inserted after clainni
		// with <p><persName> inserted
		$str = <<<XML
	<pb n="2"/>
		Is iat sain .xu. primchenela clainni</p>
	
		Iaf&eacute;d. cona fochenelaib.<lb n="28"/>
		ro selbsat feranna imda isinn Asia &oacute; Sl&eacute;ib
		Imai &ampersir; o<lb n="29"/>
		Sleib Tuir co Sruth Tanai &ampersir; connici in Scithia.
		&ampersir; ro<lb n="30"/>
		&sdot;elbsat in nEoraip uli connici in n-ac&iacute;an muridi
		fuineta<lb n="31"/>
		Insi Bretan &ampersir; in nEspain ulide.</p>
	
		<p><lb n="32"/>De chlannaib Iafeth m<ex>ei</ex>c Noi connici so cona<lb n="33"/>
		pr&iacute;mchenelaib &ampersir; cona &ndot;gabalaib
		&ampersir; a ferannaib et<ex>er</ex><lb n="34"/>
		Asia &ampersir; Eoraip</p>
		</div2>
		<div2 type="section">
		<head><lb n="35"/>De chlannaib Cam m<ex>eic</ex> Noi so sis
		ifechtsa</head>
		<p><lb n="36"/>Cam &ampersir; Oliua a ben .iiii. m<ex>ei</ex>c leo .i.
		Chus &ampersir; Mesram.<lb n="37"/>
		Futh. &ampersir; Cannan. Chus uads<ex>id</ex>e Chusi.
		Ethiopia a hai<sup resp="BB">n</sup>m<lb n="38"/>
		s<ex>id</ex>e indiu. Mesram. is &uacute;ad Egiptus. Futh.
		is uad<lb n="39"/>
		Afraicdai <add type="gloss" hand="A">&lstrok; Libei</add>
		Futhei <mls n="1b" unit="folio"/>a n-ainm s<ex>id</ex>e fecht aile
		riam. &ampersir; is<lb n="40"/>
		&uacute;ad r&aacute;t<ex>er</ex> Sruth Fuith. Cannan. is
		&uacute;ad Cannannai. is he<lb n="41"/>
		a ferann s<ex>id</ex>e ro gabsat m<ex>ei</ex>c Israel iar
		tain &iacute;ar d&iacute;lgend na<lb n="42"/>
		Cannanna &ampersir; iarna n-innarba.</p>
		<p><lb n="43"/>Cus m<ex>ac</ex> Cam .uii. m<ex>ei</ex>c les .i. Saba.
		is uad Sabei.<lb n="44"/>
		Ebila. uads<ex>id</ex>e Getuli filet i ndithruib na
		hAfraice.<lb n="45"/>
		Sabatha. is uad Sabatheni. Astabar&iacute; i&mmacr; a
		n-ainm<lb n="46"/>
		indiu. Recma. Sabata. Acha. Nebroth is leis<ex>id</ex>e
		ro<lb n="47"/>
		cumtaiged in Babiloin ar th&uacute;s cia ro cumtaiged la
		N&iacute;n<lb n="48"/>
		m<ex>ac</ex> Beil &iacute;ar tain in tan ro gab r&iacute;ge
		As&aacute;r. Babilonia .i.<lb n="49"/>
		<frn lang="la">confusio</frn> .i. cumasc iarsinni ro
		cumaiscthea na berlai<lb n="50"/>
		isind luc sain &ampersir; is la Nebroth ro cumtaiged Arach
		ainm<lb n="51"/>
		aile di Edisa. &ampersir; is leis ro cumtaiged Achad
		&ampersir; Cabann &ampersir;<lb n="52"/>
		is &eacute; a hainm s<ex>id</ex>e indiu Seleucia ond
		r&iacute;g Seleucio ro<lb n="53"/>
		r&aacute;ded i mMaig Sennar atat sin ule.</p>
		<p><lb n="54"/>Is de sil Nebroith As&uacute;r &oacute; tat Asardai iar
		fairind &lstrok; is de<lb n="55"/>
		&sdot;&iacute;l S&eacute;in m<ex>eic</ex> Noi in tAsur .i.
		Asur m<ex>ac</ex> S&eacute;in m<ex>eic</ex> Noi.<lb n="56"/>
		&ampersir; is and ro genair i mMaig Senn&aacute;r
		&ampersir; is leis ro cumtaiged<lb n="57"/>
		Ninues &ampersir; Thala &ampersir; Resen .i. cathir mor fil
		et<ex>er</ex> <p><persName>Ninues &ampersir;<lb n="58"/>
	XML;
	  return $str;
	  }




}