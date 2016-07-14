<?php
/*
 * Biblioteka PHP dla Programu Partnerskiego Grupy Wydawniczej Helion
 *
 * Wersja: 1.0.0
 * �r�d�a biblioteki: https://github.com/gwhelion/HelionLib
 * Dokumentacja: https://github.com/gwhelion/HelionLib/wiki
 *
 * Autor: Pawe� Pela (paulpela.com, pawel@paulpela.com)
 * Licencja: GPL2
 *
 * Wi�cej informacji: http://program-partnerski.helion.pl
 * Forum i support: http://program-partnerski.helion.pl/forum/
 * Kana� RSS z informacjami o aktualizacjach:
 *
 *
 * Copyright 2012  GW Helion  (email : radoslaw.tosta@helion.pl)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

class HelionLib {

    private $error;

    // Cache'owanie danych

    /**
     * Tablica z�o�ona z:
     * [0] - URL �r�d�a danych
     * [1] - obiekt SimpleXML z danymi
     *
     * @var array
     */
    private $cache_xml;

    /**
     * Cache tablic z danymi o ksi��kach.
     *
     * Tylko pierwsze zapytanie o dane ksi��ki jest kierowane do serwer�w Heliona.
     * Ka�de kolejne zapytania pobierane s� ju� z cache.
     *
     * $cache_ksiazki_helion
     *  [ident]
     *      array $ksiazka
     *  [ident]
     *      array $ksiazka
     *
     * @var array
     */
    private $cache_ksiazki_helion;
    private $cache_ksiazki_onepress;
    private $cache_ksiazki_sensus;
    private $cache_ksiazki_septem;
    private $cache_ksiazki_ebookpoint;
    private $cache_ksiazki_bezdroza;

    /**
     *
     * @var array
     */
    private $cache_kategorie;

    private $cache_top;

    private $cache_nowinki;

    private $cache_w_przygotowaniu;

    private $cache_ksiazka_dnia;


    private $ksiegarnie = array(
        "helion",
        "onepress",
        "sensus",
        "septem",
        "ebookpoint",
        "bezdroza",
    );

    private $rozmiary_okladek = array(
        "65x85",
        "72x95",
        "88x115",
        "90x119",
        "120x156",
        "125x163",
        "181x236",
        "326x466",
    );

    private $partner;


    //--------------------------------------------------------------------------

    /**
     *
     * @param string $partner Identyfikator partnera, np. 1234a
     */
    public function __construct($partner) {
        if($this->val_partner($partner)) {
            $this->partner = $partner;
        } else {
            $this->error = "Podanie numeru partnera jest obowi�zkowe.";
            return false;
        }
    }

    /**
     * Zwraca link do strony g��wnej z numerem partnera.
     *
     * @param string $ksiegarnia helion|onepress itd.
     * @return string URL
     */
    public function link_do_glownej($ksiegarnia = 'helion') {

        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_partner($this->partner))
            return false;

        return 'http://' . $ksiegarnia . '.pl/view/' . $this->partner;

    }

    /**
     * Walidator ksi�garni.
     *
     */
    public function val_ksiegarnia($ksiegarnia) {

        if(!in_array($ksiegarnia, $this->ksiegarnie)) {
            $this->error = "Nieprawid�owa nazwa ksi�garni.";
            return false;
        } else {
            return true;
        }
    }

    /**
     * Walidator identyfikatora partnera.
     */
    public function val_partner($partner) {

        if($this->match_partner($partner)) {
            return true;
        } else {
            $this->error = "Walidacja id partnera nie powiod�a si�.";
            return false;
        }

    }

    private function match_partner($partnerid) {
        return preg_match('/^[0-9]{4}[0-9a-zA-Z.]{1}$/', $partnerid);
    }

    /**
     * Zwraca numer partnera.
     */
    public function get_partner() {
        return $this->partner;
    }

    public function set_partner($partner) {
        if($this->val_partner($partner)) {
            $this->partner = $partner;
            return true;
        } else {
            return false;
        }
    }

    public function strip_ident($ident) {

        if(!$this->val_ident($ident)) {
            $this->error = "Niepoprawny identyfikator ksi��ki (ident).";
            return false;
        }

        if(preg_match("/_ebook$/", $ident)) {
            $temp_ident = explode("_ebook", $ident);
            $ident = $temp_ident[0];
        } else if (preg_match("/_p$/", $ident)) {
            $temp_ident = explode("_p", $ident);
            $ident = $temp_ident[0];
        } else if (preg_match("/_e$/", $ident)) {
            $temp_ident = explode("_e", $ident);
            $ident = $temp_ident[0];
        } else if (preg_match("/_m$/", $ident)) {
            $temp_ident = explode("_m", $ident);
            $ident = $temp_ident[0];
        }

        return $ident;
    }

    public function val_ident($ident) {
        if(!preg_match("/^[a-z0-9_]+$/", $ident)) {
            $this->error = "Niepoprawny identyfikator ksi��ki (ident).";
            return false;
        } else {
            return true;
        }
    }

    /**
     * Zwraca aktualny komunikat b��du.
     *
     * @return string Komunikat b��du
     */
    public function get_error() {
        return $this->error;
    }

    /**
     *
     * @return string
     */
    public function okladka() {

        $num_args = func_num_args();

        if($num_args == 2) {
            $ksiazka = func_get_arg(0);

            $ksiegarnia = $ksiazka['ksiegarnia'];
            $ident = $ksiazka['ident'];
            $rozmiar = func_get_arg(1);
        } else if($num_args == 1) {
            $ksiazka = func_get_arg(0);

            $ksiegarnia = $ksiazka['ksiegarnia'];
            $ident = $ksiazka['ident'];
            $rozmiar = "120x156";
        } else if ($num_args == 3) {
            $ksiegarnia = func_get_arg(0);
            $ident = func_get_arg(1);
            $rozmiar = func_get_arg(2);
        } else {
            $this->error = 'Nieprawid�owa liczba argument�w.';
            return false;
        }

        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_ident($ident))
            return false;

        if(!$this->val_rozmiar($rozmiar))
            return false;

        $ident = $this->strip_ident($ident);

        return "http://" . $ksiegarnia . ".pl/okladki/" . $rozmiar . "/" . $ident . ".jpg";
    }

    /**
     * Testuje, czy podany rozmiar ok�adki jest prawid�owy.
     *
     * @param string $rozmiar np. 120x156
     * @return bool
     */
    public function val_rozmiar($rozmiar) {
        if(in_array($rozmiar, $this->rozmiary_okladek)) {
            return true;
        } else {
            $this->error = "Nieprawid�owy rozmiar ok�adki.";
            return false;
        }
    }

    /**
     * Zwraca link do strony z promocjami w podanej ksi�garni.
     */
    public function link_promocje($ksiegarnia, $partnerid = null) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if($partnerid && !$this->val_partner($partnerid)) {
            return false;
        } else if (!$partnerid) {
            $partnerid = $this->get_partnerid();
        }

        if(!$partnerid)
            return false;

        return "http://" . $ksiegarnia . ".pl/page/" . $partnerid . "/promocje";
    }

    /**
     * Zwraca link do pliku XML z list� kategorii.
     *
     */
    public function lista_kategorii_xml($ksiegarnia = 'helion') {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        return 'http://' . $ksiegarnia . '.pl/plugins/new/xml/lista-serie.cgi';
    }

    /**
     * Zwraca link do pliku XML z list� serii wydawniczych.
     */
    public function lista_serii_xml($ksiegarnia = 'helion') {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        return 'http://' . $ksiegarnia . '.pl/plugins/new/xml/lista-katalog.cgi';
    }

    /**
     * Zwraca adres URL z numerem partnera, kieruj�cy do kategorii w wybranej ksi�garni.
     *
     * TODO!!! - identyfikator kategorii
     *
     * @param string $ksiegarnia np. "helion", "onepress" itd.
     * @param string $kategoria np.
     * @param string $partner Identyfikator partnera (opcjonalny), domy�lnie pobierany z $this->partner
     * @return string Adres URL kategorii wraz z numerem partnera.
     */
    public function link_do_kategorii($ksiegarnia, $kategoria, $partner = null) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_kategoria($kategoria))
            return false;

        if($partner && !$this->val_partner($partner))
            return false;

        if(!$partner)
            $partner = $this->get_partnerid();

        return 'http://' . $ksiegarnia . '.pl/page/' . $partner . '/katalog/' . $kategoria . '.html.htm';
    }

    /**
     * Zwraca adres URL z numerem partnera, kieruj�cy do serii wydawniczej w wybranej ksi�garni.
     *
     * TODO!!! - identyfikator serii
     *
     * @param string $ksiegarnia np. "helion", "onepress" itd.
     * @param string $seria np.
     * @param string $partner Identyfikator partnera (opcjonalny), domy�lnie pobierany z $this->partner
     * @return string Adres URL serii wraz z numerem partnera.
     */
    public function link_do_serii($ksiegarnia, $seria, $partner = null) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_seria($seria))
            return false;

        if($partner && !$this->val_partner($partner))
            return false;

        if(!$partner)
            $partner = $this->get_partnerid();

        return 'http://' . $ksiegarnia . '.pl/page/' . $partner . '/katalog/' . $seria . '.html.htm';
    }

    /**
     *
     * @param string $kategoria 28,0,0
     * @return bool
     */
    public function val_kategoria($kategoria) {
        if($this->match_kategoria($kategoria)) {
            return true;
        } else {
            $this->error = "Walidacja identyfikatora nie powiod�a si�.";
            return false;
        }
    }

    private function match_kategoria($kategoria) {
        return preg_match('/^[0-9,]$/', $kategoria);
    }

    /**
     *
     * @param string $seria 28,0,0
     * @return bool
     */
    public function val_seria($seria) {
        return $this->val_kategoria($seria);
    }

    /**
     * Zwraca URL z numerem partnera, prowadz�cy do wybranej ksi��ki.
     *
     * @param string $ksiegarnia np. helion, onepress itp.
     * @param string $ident Identyfikator ksi��ki
     * @param int $cyfra Dodatkowy parametr, pozwalaj�cy dok�adniej �ledzi� konwersje. Zakres 0-255.
     * @param string $partner Identyfikator partnera. Domy�lnie pobierany z $this->partner
     * @return string URL z numerem partnera, prowadz�cy do wybranej ksi��ki.
     */
    public function link_do_ksiazki($ksiegarnia, $ident, $cyfra = null, $partner = null) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_ident($ident))
            return false;

        if($cyfra && !$this->val_cyfra($cyfra))
            return false;

        $partner = $this->partner;

        if(!$this->val_partner($partner))
            return false;

        if($cyfra) {
            return 'http://' . $ksiegarnia . '.pl/view/' . $partner . '/' . $cyfra . '/' . $ident . '.htm';
        } else {
            return 'http://' . $ksiegarnia . '.pl/view/' . $partner . '/' . $ident . '.htm';
        }

    }

     /**
     * Zwraca URL z numerem partnera, dodaj�cy wybran� ksi��k� do koszyka.
     *
     * @param string $ksiegarnia np. helion, onepress itp.
     * @param string $ident Identyfikator ksi��ki
     * @param int $cyfra Dodatkowy parametr, pozwalaj�cy dok�adniej �ledzi� konwersje. Zakres 0-255.
     * @param string $partner Identyfikator partnera. Domy�lnie pobierany z $this->partner
     * @return string URL z numerem partnera, dodaj�cy wybran� ksi��k� do koszyka.
     */
    public function link_do_koszyka($ksiegarnia, $ident, $cyfra = null) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_ident($ident))
            return false;

        if($cyfra && !$this->val_cyfra($cyfra))
            return false;

        $partner = $this->partner;

        if(!$this->val_partner($partner))
            return false;

        if($cyfra) {
            return 'http://' . $ksiegarnia . '.pl/add/' . $partner . '/' . $cyfra . '/' . $ident . '.htm';
        } else {
            return 'http://' . $ksiegarnia . '.pl/add/' . $partner . '/' . $ident . '.htm';
        }

    }

    /**
     * Walidator dla parametru cyfra.
     *
     * Walidator zwraca true je�li podana $cyfra jest poprawna, w przeciwnym razie
     * zwraca false i ustawia $this->error.
     *
     * @param int $cyfra parametr z zakresu 0-255
     * @return bool
     */
    public function val_cyfra($cyfra) {
        if(is_int($cyfra) && ($cyfra >= 0 && $cyfra < 256)) {
            return true;
        } else {
            $this->error = 'Parametr "cyfra" musi by� liczb� ca�kowit� z zakresu 0-255.';
            return false;
        }
    }

    /**
     * Zwraca URL do kana�u RSS z ksi��kami z danej kategorii oraz kodem partnera w linkach.
     *
     * @param string $ksiegarnia helion | onepress | sensus itd.
     * @param string $kategoria np. 28,0,0
     * @return string URL do RSS
     */
    public function rss_kategoria($ksiegarnia, $kategoria) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_kategoria($kategoria))
            return false;

        if(!$this->val_partner($this->partner))
                return false;

        return 'http://' . $ksiegarnia . '.pl/rss/index.cgi?k=' . $kategoria . '&nr=' . $this->partner;
    }

    /**
     * Zwraca URL do kana�u RSS z ksi��kami z danej serii oraz kodem partnera w linkach.
     *
     * @param string $ksiegarnia helion | onepress | sensus itd.
     * @param string $seria np. 28,0,0
     * @return string URL do RSS
     */
    public function rss_seria($ksiegarnia, $seria) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_seria($seria))
            return false;

        if(!$this->val_partner($this->partner))
                return false;

        return 'http://' . $ksiegarnia . '.pl/rss/index.cgi?s=' . $seria . '&nr=' . $this->partner;
    }

    private function get_xml($url) {

        switch($this->detect_connection_method()) {
            case 'curl':
                return $this->get_xml_with_curl($url);
            case 'fopen':
                return $this->get_xml_with_fopen($url);
            default:
                return false;
        }
    }

    private function detect_connection_method() {
        if($this->is_curl_enabled()) {
            return "curl";
        } else if($this->is_allow_url_fopen_enabled()) {
            return "fopen";
        } else {
            $this->error = '�adna z metod pobierania danych nie jest dost�pna. Wymagany jest dost�p przez cURL albo przez fopen.';
            return false;
        }
    }

    /**
     *
     * @return bool
     */
    private function is_curl_enabled() {
        return (in_array('curl', get_loaded_extensions())) ? true : false;
    }

    /**
     *
     * @return bool
     */
    private function is_allow_url_fopen_enabled() {
        return (ini_get('allow_url_fopen') == 1) ? true : false;
    }

    private function get_xml_with_curl($url) {
        if($this->cache_xml[0] == $url)
            return $this->cache_xml[1];

        $cu = @curl_init();
        @curl_setopt($cu, CURLOPT_URL, $url);
        @curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
        $xml = simplexml_load_string(@curl_exec($cu));
        @curl_close($cu);

        if(is_object($xml)) {
            $this->cache_xml[0] = $url;
            $this->cache_xml[1] = $xml;

            return $xml;
        } else {
            $this->error = 'Pobranie danych XML zako�czy�o si� niepowodzeniem. Serwer nie zwr�ci� danych XML lub dane by�y niepoprawne.';
            return false;
        }
    }

    private function get_xml_with_fopen($url) {
        if($this->cache_xml[0] == $url)
            return $this->cache_xml[1];

        $xml = @simplexml_load_file($url);

        if(is_object($xml)) {
            $this->cache_xml[0] = $url;
            $this->cache_xml[1] = $xml;

            return $xml;
        } else {
            $this->error = 'Pobranie danych XML zako�czy�o si� niepowodzeniem. Serwer nie zwr�ci� danych XML lub dane by�y niepoprawne.';
            return false;
        }

    }

    /**
     * Zwraca tablic� z danymi na temat ksi��ki.
     *
     * Ta funkcja korzysta z prostego cache'owania, tak wi�c wielokrotne zapytania
     * o t� sam� ksi��k� wykonane pod rz�d b�d� obs�ugiwane z cache'u.
     *
     * @param string $ksiegarnia np. "helion", "ebookpoint"
     * @param string $ident np. "grywal", "markwy"
     * @return array
     */
    public function ksiazka($ksiegarnia, $ident) {

        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(!$this->val_ident($ident))
            return false;

        if(!empty($this->cache_ksiazki_{$ksiegarnia}[$ident]))
            return $this->cache_ksiazki_{$ksiegarnia}[$ident];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/ksiazka.cgi?ident=' . $ident);

        if($xml) {
            $ksiazka = $this->parser_xml_ksiazka($xml, $ksiegarnia);

            if(is_array($ksiazka)) {
                $this->cache_ksiazki_{$ksiegarnia}[$ident] = $ksiazka;
                //convert utf to iso
                $ksiazka['tytul'][0] = iconv("UTF-8", "ISO-8859-2", $ksiazka['tytul'][0]);

                return $ksiazka;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    /**
     * Przetwarza obiekt XML na tablic� z informacjami o ksi��ce.
     *
     * @param object $xml
     * @param string $ksiegarnia
     * @return array
     */
    private function parser_xml_ksiazka($xml, $ksiegarnia) {
        $a = json_decode(json_encode((array) $xml),1);

        if(is_array($a)) {
            $a['ident'] = strtolower($a['ident']);
            $a['ksiegarnia'] = $ksiegarnia;
            $a['opis'] = (string) $xml->opis;
            return $a;
        } else {
            $this->error = 'Nie uda�o si� przetworzenie danych o ksi��ce do tablicy.';
            return false;
        }
    }

    private function parser_xml_top($xml) {
        $a = json_decode(json_encode((array) $xml),1);

        if(is_array($a)) {
            $a = $a['PRODUKT'];

            $i = 1;
            foreach($a as $top) {
                $b[$i] = strtolower($top["@attributes"]["ID"]);
                $i++;
            }

            return $b;
        } else {
            $this->error = 'Nie uda�o si� przetworzenie danych z listy TOP20.';
            return false;
        }
    }

    /**
     * Zwraca tablic� z list� najpopularniejszych ksi��ek w danej ksi�garni (TOP20).
     *
     * Indeksy tablicy odpowiadaj� miejscu na li�cie:
     * $top[3] - ident ksi��ki zajmuj�cej 3 miejsce
     * Miejsca s� liczone od 1 do 20, nie od 0 do 19;
     *
     * Metoda korzysta z cache'owania - wielokrotne zapytania o t� sam� list� b�d�
     * obs�ugiwane z cache'u. Tylko pierwsze pobranie listy powoduje wys�anie zapytania
     * do serwer�w Helion.
     *
     * @param string $ksiegarnia helion | onepress | sensus itd.
     * @return array
     */
    public function top($ksiegarnia = 'helion', $catId) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(isset($this->cache_top[$ksiegarnia]))
            return $this->cache_top[$ksiegarnia];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/top.cgi?k='.$catId);

        $top = $this->parser_xml_top($xml);

        if(!$top)
            return false;

        $this->cache_top[$ksiegarnia] = $top;

        return $top;
    }

    private function parser_xml_nowinki($xml) {
        $a = json_decode(json_encode((array) $xml),1);
        //$a = (array) $xml;

        if(is_array($a)) {
            $a = $a['item'];

            // TODO - opis z xmlu...
            foreach($a as $item) {
                print_r($xml->xpath('//item[@nr="' . $item['@attributes']['nr'] . '"]'));
                $b[$item['@attributes']['nr']] = array('data' => $item['@attributes']['data'],
                    'opis' => (string) $xml->xpath('//item[@nr="' . $item['@attributes']['nr'] . '"]/opis'));
            }

            return $b;
        } else {
            $this->error = 'Nie uda�o si� przetworzenie danych o nowinkach wydawniczych do tablicy.';
            return false;
        }
    }

    public function nowinki($ksiegarnia) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(isset($this->cache_nowinki[$ksiegarnia]))
                return $this->cache_nowinki[$ksiegarnia];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/nowinki.cgi');

        $nowinki = $this->parser_xml_nowinki($xml);

        if(!$nowinki)
            return false;

        $this->cache_nowinki[$ksiegarnia] = $nowinki;

        return $nowinki;
    }

    public function w_przygotowaniu($ksiegarnia) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(isset($this->cache_w_przygotowaniu[$ksiegarnia]))
                return $this->cache_w_przygotowaniu[$ksiegarnia];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/lista.cgi?status=2&druk=0');

        $w_przygotowaniu = $this->parser_xml_w_przygotowaniu($xml);

        if(!$w_przygotowaniu)
            return false;

        $this->cache_w_przygotowaniu[$ksiegarnia] = $w_przygotowaniu;

        return $w_przygotowaniu;
    }

    private function parser_xml_w_przygotowaniu($xml) {
        $a = json_decode(json_encode((array) $xml),1);

        if(is_array($a)) {
            $a = $a['item'];

            foreach($a as $item) {
                $b[] = array(
                    "isbn" => $item['@attributes']['isbn'],
                    "ean" => $item['@attributes']['ean'],
                    "ident" => strtolower($item['@attributes']['ident']),
                    "tytul" => $item['@attributes']['tytul'],
                    "autor" => $item['@attributes']['autor'],
                    "znizka" => $item['@attributes']['znizka'],
                );
            }
            return $b;
        } else {
            $this->error = 'Nie uda�o si� przetworzenie danych o ksi��kach w przygotowaniu do tablicy.';
            return false;
        }
    }

    /**
     * Zwraca tablic� z list� kategorii w danej ksi�garni.
     *
     * Tablica jest podzielona na dwie cz�ci: nad i pod
     *
     * W przypadku ksi�garni Helion i Ebookpoint opr�cz kategorii mamy kategorie
     * i podkategorie. Pozosta�e ksi�garnie maj� tylko kategorie, a cz�� 'pod'
     * pozostaje pusta.
     *
     * Metoda korzysta z cache'owania.
     *
     * @param string $ksiegarnia np. helion|onepress|ebookpoint
     * @return array
     */
    public function kategorie($ksiegarnia) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(isset($this->cache_kategorie[$ksiegarnia]))
                return $this->cache_kategorie[$ksiegarnia];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/lista-katalog.cgi');

        $kategorie = $this->parser_xml_kategorie($xml);

        if(!$kategorie)
            return false;

        $this->cache_kategorie[$ksiegarnia] = $kategorie;

        return $kategorie;
    }

    private function parser_xml_kategorie($xml) {
        if(!is_object($xml)) {
            $this->error = "Lista kategorii XML nie jest poprawnym obiektem SimpleXML.";
            return false;
        }

        $lista = array("nad" => array(), "pod" => array());

		foreach($xml as $item) {
			$grupa_nad = (string) $item->attributes()->grupa_nad;
			$id_nad = (string) $item->attributes()->id_nad;

			$grupa_pod = (string) $item->attributes()->grupa_pod;
			$id_pod = (string) $item->attributes()->id_pod;

			$lista['nad'][$id_nad] = $grupa_nad;

			if($id_pod) {
				$lista['pod'][$id_pod] = array($id_nad => $grupa_pod);
			}
		}

        return $lista;
    }

    /**
     * Zwraca tablic� z danymi na temat aktualnej ksi��ki w promocji dnia.
     *
     * Dane zawarte w tablicy:
     * [isbn]
     * [ean]
     * [ident]
     * [tytul]
     * [autor]
     * [cena] - cena po uwzgl�dnieniu zni�ki
     * [cenadetaliczna] - normalna cena (bez zni�ki)
     * [znizka] - zni�ka w procentach
     * [status]
     * [marka]
     * [ts] - timestamp
     *
     * @param string $ksiegarnia
     * @return array
     */
    public function ksiazka_dnia($ksiegarnia) {
        if(!$this->val_ksiegarnia($ksiegarnia))
            return false;

        if(isset($this->cache_ksiazka_dnia[$ksiegarnia]))
                return $this->cache_ksiazka_dnia[$ksiegarnia];

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/xml/lista.cgi?pd=1');

        $ksiazka_dnia = $this->parser_xml_ksiazka_dnia($xml);

        if(!$ksiazka_dnia)
            return false;

        $this->cache_ksiazka_dnia[$ksiegarnia] = $ksiazka_dnia;

        return $ksiazka_dnia;
    }

    private function parser_xml_ksiazka_dnia($xml) {
        $a = json_decode(json_encode((array) $xml),1);

        if(is_array($a)) {
            $a = $a['item']['@attributes'];
            $a['ident'] = strtolower($a['ident']);

            return $a;
        } else {
            $this->error = 'Nie uda�o si� przetworzenie danych o ksi��ce dnia.';
            return false;
        }
    }


    //--------------------------------------------------------------------------
    //
    // Operacje na tablicy $ksiazka
    //
    //--------------------------------------------------------------------------

    // TODO: przerobi� na u�ywanie func_get_arg i func_num_args

    /**
     * Okre�la czy podana ksi��ka to ebook.
     *
     * @param mixed $ksiazka
     * @param string $ident
     * @return bool
     */
    public function is_ebook() {

        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        if(isset ($ksiazka['ebook_formaty'])) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Testuje, czy ksi��ka jest bestsellerem.
     *
     * @param mixed $ksiazka tablica $ksiazka lub nazwa ksi�garni
     * @param string $ident identyfikator ksi��ki
     * @return bool
     */
    public function is_bestseller() {
        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        if($ksiazka['bestseller'] == '1') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Testuje, czy ksi��ka jest nowo�ci�.
     *
     * @param array $ksiazka
     * @return bool
     */
    public function is_nowosc() {
        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        if($ksiazka['nowosc'] == '1') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Testuje, czy ksi��ka posiada zni�k� (nie sprawdza jej wysoko�ci).
     *
     * Aby otrzyma� wysoko�� zni�ki, nale�y u�y� metody wysokosc_znizki($ksiazka).
     *
     * @param array $ksiazka
     * @return bool
     */
    public function is_znizka() {
        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        if($ksiazka['znizka'] > '0') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Testuje, czy ksi��ka jest dost�pna w druku na ��danie.
     *
     * @param array $ksiazka
     * @return bool
     */
    public function is_nazadanie() {
        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        if($ksiazka['nazadanie'] == '1') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Zwraca liczb� odpowiadaj�c� procentowej wysoko�ci zni�ki na podan� ksi��k�.
     *
     * @param mixed $ksiazka tablica z danymi lub nazwa ksi�garni
     * @param string $ident identyfikator ksi��ki (opcjonalny)
     *
     * @return int
     */
    public function wysokosc_znizki() {
        $num_args = func_num_args();

        if($num_args == 1) {
            $ksiazka = func_get_arg(0);
        } else if ($num_args == 2) {
            $ksiazka = $this->ksiazka(func_get_arg(0), func_get_arg(1));
        } else {
            return null;
        }

        return $ksiazka['znizka'];
    }
}

?>