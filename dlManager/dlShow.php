<?php

/**************************************************************************************************\
 * MISE A JOUR LE
 * ==============
 * 13 Juin 2011
 *
 * AUTEUR
 * ==============
 * Florian Le Frioux (florian@lefrioux.fr)
 *
 * NOTE!
 * ==============
 * dlShow.php − Page "robot" permettant la détection des nouveaux épisodes des séries
 * suivies, et entre autres gère l'ajout en BDD de leurs noms et nicknames
 *
\**************************************************************************************************/

// Récupération des url des flux
$reqSerie = Mysql::query("SELECT showID FROM serie WHERE visible = '1'");
while ($donneesSerie = $reqSerie->fetch_row())
{
	// Téléchargement des flux TVU.org.ru	http://tvunderground.org.ru/rss(t).php?se_id=xxxx
	if (stristr($donneesSerie[0], 'tvu')) {
		$showID = mb_strrichr($donneesSerie[0], '_tvu', TRUE);
		$rssURL = 'http://tvunderground.org.ru/rsst.php?se_id='.$showID;
		$showID .= '_tvu';
	}

	// Téléchargement des flux ezRSS.it		http://www.ezrss.it/search/index.php?showName=xxxx&mode=rss
	elseif (stristr($donnees_serie[0], 'ezrss')) {
		$showID = mb_strrichr($donnees_serie[0], '_ezrss', TRUE);
		$rssURL = 'http://www.ezrss.it/search/index.php?showName='.$showID.'&mode=rss';
		$showID .= '_ezrss';
	}


	/* * * * Récupération des différentes données * * * */

	$xml = new SimpleXMLElement($rssURL, NULL, TRUE);

	if (stristr($rssURL, 'rsst.php'))  // pour TVU.org.ru
	{
		foreach ($xml->channel as $v)
        {
		//<title>[torrent] tvunderground.org.ru: Dexter - Season 4 (HDTV) english</title>

			$showName = $v->title->asXML();
			$showName = substr($showName, 39);
			$showName = stristr($showName,'-',TRUE); //mb_stristr
			$showName = trim($showName); // On récupère le nom de la série
		}

		foreach ($xml->channel->item as $v)
        {
		//<title>[torrent] Dexter - 4x12 - The Getaway</title>
		//<guid>http://tvunderground.org.ru/torrent.php?tid=2356</guid>

			$SaisonEpisode = $v->title->asXML();
			$SaisonEpisode = mb_strrichr($SaisonEpisode, ' - ', TRUE);
			$SaisonEpisode = mb_stristr($SaisonEpisode, ' - ');
			$SaisonEpisode = substr($SaisonEpisode, 3);
			$SaisonEpisode = trim($SaisonEpisode);
			/*
			$SaisonEpisode = mb_stristr($SaisonEpisode, ' - ');
			$SaisonEpisode = substr($SaisonEpisode, 3);
			$SaisonEpisode = mb_stristr($SaisonEpisode, ' - ', TRUE);
			*/
			$ar_SaisonEpisode[] = $SaisonEpisode;	// On récupère le numéro de la saison et de l'episode

			$tid = $v->guid->asXML();
			$tid = substr($tid, 50, -7);
			$tid .= '_tvu';
			$ar_tid[] = $tid;	// On récupère l'identifiant du torrent de l'episode, qui sert aussi d'ID "unique" de l'episode
		}
	}
	elseif (stristr($rssURL, 'ezrss.it'))
	{
		foreach ($xml->channel->item as $v)
        {
		//<link>http://torrent.zoink.it/Dexter.S05E09.HDTV.XviD-FEVER.[eztv].torrent</link>
		//<category domain="http://eztv.it/shows/78/dexter/"><![CDATA[TV Show / Dexter]]></category>
		//<description><![CDATA[Show Name: Dexter; Episode Title: N/A; Season: 5; Episode: 9]]></description>
		//<comments>http://eztv.it/forum/discuss/24173/</comments>

			$url = $v->link->asXML();
			$url = substr($url, 6, -7);
			$ar_url[] = $url;		// On récupère le lien du torrent de l'episode

			$showName = $v->category->asXML();
			$showName = mb_stristr($showName,'/ ');
			$showName = substr($showName, 2,-14);	// On récupère le nom de la série

			$ligne = $v->description->asXML();
			$nbSaison = stristr($ligne, 'Season: ');
			$nbSaison = substr($nbSaison, 8);
			$nbSaison = stristr($nbSaison, '; Ep', TRUE);	// On récupère le numéro de la saison

			$nbEpisode = stristr($ligne,'Episode: ');
			$nbEpisode = substr($nbEpisode, 9);
			$nbEpisode = stristr($nbEpisode, ']]>', TRUE);	// On récupère le numéro de l'episode

			$SaisonEpisode = $nbSaison.'x'.$nbEpisode;
			$ar_SaisonEpisode[] = $SaisonEpisode;

			$tid = $v->comments->asXML();
			$tid = stristr($tid,'discuss/');
			$tid = substr($tid, 8);
			$tid = stristr($tid, '/</comments>', TRUE);
			$tid .= '_ezrss';								// On récupère l'identifiant "unique" de l'episode
			if ($tid != '_ezrss')
				$ar_tid[] = $tid;
		}
	}


	/* * * * Traitements des différentes données récupérées * * * */

	// Traitement des torrentIDs

	if (count($ar_tid) >= 1) {
		asort($ar_tid);
		foreach ($ar_tid as $key => $tid)
		{
			$req_oldTID = Mysql::query("SELECT oldTID, showNick FROM serie WHERE showID = '".$showID."'");

			while ($donneesTID = $req_oldTID->fetch_row())
			{
				if ($donneesTID[0] != 0) {
					$oldTID = Fonctions::triSelonSite($donneesTID[0]);
				}
				else $oldTID = '0';

				$newTID = Fonctions::triSelonSite($tid);

				if ($newTID > $oldTID) 			// $oldTID et $newTID sont des entiers (ne comprenant pas '_tvu' / '_ezrss') contrairement a $donneesTID[0] et $tid respectivement
				{
					if (stristr($showID, '_tvu'))
                    {
						$torURL = 'http://tvunderground.org.ru/torrent.php?tid='.$newTID; // http://tvunderground.org.ru/torrent.php?tid=xxxxxx 	URL Torrent
						$torLocal = SEEDB_PATH.'/torrent_'.$donneesTID[1].'_'.$newTID.'.torrent';
						$cmdTor = '/usr/bin/wget -O '.$torLocal.' '.$torURL;
						exec($cmdTor);
						$nbDL ++;
					}
					elseif (stristr($showID, '_ezrss'))
                    {
						asort($ar_url);
						foreach ($ar_url as $key => $torURL) {
							$torLocal = SEEDB_PATH.'/torrent_'.$newTID.'.torrent';
							$cmdTor = '/usr/bin/wget -O '.$torLocal.' '.$torURL;
							exec($cmdTor);
							$nbDL ++;
							unset($ar_url[$torURL]);
							break;
						}
					}
					Mysql::query("UPDATE serie SET oldTID = '".$tid."' WHERE showID = '".$showID."'");
				}
			}
		}
		unset($ar_tid);
		$req_oldTID->free();
	}


	// Traitement pour le numéro du dernier épisode téléchargé

	if (count($ar_SaisonEpisode) >= 1) {
		asort($ar_SaisonEpisode);
		foreach ($ar_SaisonEpisode as $key => $lastEp) {
			// Si l'episode téléchargé contient une saison complète (saison terminée, donc), on set le champ visible à 0 sur la saison concernée
			if (stristr($lastEp, '-') === FALSE)
				Mysql::query("UPDATE serie SET lastEpisode = '".$lastEp."' WHERE showID = '".$showID."'");
			else
				Mysql::query("UPDATE serie SET visible = '0' WHERE showID = '".$showID."'");
		}
		unset($ar_SaisonEpisode);
	}


	// Traitement du nom et détermination du nick de la série

	if (stristr($showName, '('))
		$nickTemp = stristr($showName, ' (', TRUE);
	else
        $nickTemp = $showName;

	if (stristr($nickTemp, ' '))
		$showNick = str_ireplace(' ', '.', $nickTemp);
	else
        $showNick = $nickTemp;

	if (stristr($showNick, "'"))
		$showNick = str_ireplace("'", "", $showNick);

	$showNick .= '.';

	$reqShowDetails = Mysql::query("SELECT lastEpisode FROM serie WHERE showID = '".$showID."'");

	while ($donneesShow = $reqShowDetails->fetch_row())
	{
		$showSeason = mb_stristr($donneesShow[0], 'x', TRUE);
		Mysql::query("UPDATE serie SET showName = '".Mysql::escape($showName)."', showNick = '".Mysql::escape($showNick)."',
			showSeason = '".Mysql::escape($showSeason)."' WHERE showID = '".$showID."'");
	}
}
$reqShowDetails->free();
$reqSerie->free();
?>
