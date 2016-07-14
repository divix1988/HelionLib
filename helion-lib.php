<?php
/*
 * Fork Biblioteki PHP dla Programu Partnerskiego Helion
 *
 * Wersja: 2.0.0
 *
 * Autor: Adam Omelak divix1988@gmail.com
 * Licencja: GPL2
 */

class HelionLib
{
    private $error;

    /**
     * Tablica z³o¿ona z:
     * [0] - URL Ÿród³a danych
     * [1] - obiekt SimpleXML z danymi
     *
     * @var array
     */
    private $cache_xml;

    /**
     * Cache tablic z danymi o ksi¹¿kach.
     *
     * Tylko pierwsze zapytanie o dane ksi¹¿ki jest kierowane do serwerów Heliona.
     * Ka¿de kolejne zapytania pobierane s¹ ju¿ z cache.
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
        'helion',
        'onepress',
        'sensus',
        'septem',
        'ebookpoint',
        'bezdroza',
    );

    private $rozmiary_okladek = array(
        '65x85',
        '72x95',
        '88x115',
        '90x119',
        '120x156',
        '125x163',
        '181x236',
        '326x466',
    );

    private $partner;
    private $ksiegarnia;

    /**
     *
     * @param string $partnerId Identyfikator partnera, np. 1234a
     */
    public function __construct($partnerId, $ksiegarnia) {
        if ($this->val_partner($partnerId)) {
            $this->partner = $partnerId;
            $this->ksiegarnia = $ksiegarnia;
        } else {
            $this->displayError('Nieprawid³owy numer parnera');
            return false;
        }
        //error_reporting(E_ALL);
        require_once(__DIR__.'/simple_html_dom.php');
    }

    /**
     * Walidator ksiêgarni.
     *
     */
    public function val_ksiegarnia($ksiegarnia) {

        if (!in_array($ksiegarnia, $this->ksiegarnie)) {
            $this->displayError('Nieprawid³owa nazwa ksiêgarni.');
            return false;
        } else {
            return true;
        }
    }

    /**
     * Walidator identyfikatora partnera.
     */
    public function val_partner($partner) {

        if ($this->match_partner($partner)) {
            return true;
        } else {
            $this->displayError('Walidacja id partnera nie powiod³a siê.');
            return false;
        }

    }

    public function set_partner($partner) {
        if ($this->val_partner($partner)) {
            $this->partner = $partner;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Testuje, czy podany rozmiar ok³adki jest prawid³owy.
     *
     * @param string $rozmiar np. 120x156
     * @return bool
     */
    public function val_rozmiar($rozmiar) {
        if (in_array($rozmiar, $this->rozmiary_okladek)) {
            return true;
        } else {
            $this->displayError('Nieprawid³owy rozmiar ok³adki.');
            return false;
        }
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
            $this->displayError('Walidacja identyfikatora nie powiod³a siê.');
            return false;
        }
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
     * Zwraca URL z numerem partnera, prowadz¹cy do wybranej ksi¹¿ki.
     *
     * @param string $ksiegarnia np. helion, onepress itp.
     * @param string $ident Identyfikator ksi¹¿ki
     * @param int $cyfra Dodatkowy parametr, pozwalaj¹cy dok³adniej œledziæ konwersje. Zakres 0-255.
     * @param string $partner Identyfikator partnera. Domyœlnie pobierany z $this->partner
     * @return string URL z numerem partnera, prowadz¹cy do wybranej ksi¹¿ki.
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

        if ($cyfra) {
            return 'http://' . $ksiegarnia . '.pl/view/' . $partner . '/' . $cyfra . '/' . $ident . '.htm';
        } else {
            return 'http://' . $ksiegarnia . '.pl/view/' . $partner . '/' . $ident . '.htm';
        }

    }

    /**
     * Walidator dla parametru cyfra.
     *
     * Walidator zwraca true jeœli podana $cyfra jest poprawna, w przeciwnym razie
     * zwraca false i ustawia $this->error.
     *
     * @param int $cyfra parametr z zakresu 0-255
     * @return bool
     */
    public function val_cyfra($cyfra)
    {
        if(is_int($cyfra) && ($cyfra >= 0 && $cyfra < 256)) {
            return true;
        } else {
            $this->error = 'Parametr "cyfra" musi byæ liczb¹ ca³kowit¹ z zakresu 0-255.';
            return false;
        }
    }

    /**
     * Zwraca tablicê z list¹ najpopularniejszych ksi¹¿ek w danej ksiêgarni (TOP20).
     *
     * Indeksy tablicy odpowiadaj¹ miejscu na liœcie:
     * $top[3] - ident ksi¹¿ki zajmuj¹cej 3 miejsce
     * Miejsca s¹ liczone od 1 do 20, nie od 0 do 19;
     *
     * Metoda korzysta z cache'owania - wielokrotne zapytania o tê sam¹ listê bêd¹
     * obs³ugiwane z cache'u. Tylko pierwsze pobranie listy powoduje wys³anie zapytania
     * do serwerów Helion.
     *
     * @param string $ksiegarnia helion | onepress | sensus itd.
     * @return array
     */
    public function top(array $params)
    {
        if (!isset($params['ksiegarnia'])) {
            $params['ksiegarnia'] = $this->ksiegarnia;
        } else if (!$this->val_ksiegarnia($params['ksiegarnia'])) {
            $this->displayError('ksiêgarnia nie jest ustawiona');
            return false;
        }
        if (!isset($params['cat'])) {
            $params['cat'] = '';
        }
        if (!isset($params['pod_cat'])) {
            $params['pod_cat'] = '';
        }
        if (isset($this->cache_top[$params['ksiegarnia'].$params['cat'].'/'.$params['pod_cat']])) {
            return $this->cache_top[$params['ksiegarnia'].$params['cat'].'/'.$params['pod_cat']];
        }

        $response = $this->getDom('http://'.$params['ksiegarnia'].'.pl/kategorie/'.$params['cat'].'/'.$params['pod_cat']);
        $parsedDom = $this->parseDomTop($response);

        if (isset($params['image_size'])) {
            for ($i = 0; $i < count($parsedDom); $i++) {
                //update image size
                $parsedDom[$i]['image'] = str_replace('65x85', $params['image_size'], $parsedDom[$i]['image']);
            }
        }

        if (isset($params['limit']) && count($parsedDom) > $params['limit']) {
            $parsedDom = array_slice($parsedDom, 0, $params['limit']);
        }
        if (isset($params['random']) && $params['random'] === true) {
            shuffle($parsedDom);
        }

        $this->cache_top[$params['ksiegarnia'].$params['cat_id'].'/'.$params['pod_cat']] = $parsedDom;

        return $parsedDom;
    }

    /**
     * Zwraca liczbê odpowiadaj¹c¹ procentowej wysokoœci zni¿ki na podan¹ ksi¹¿kê.
     *
     * @param mixed $ksiazka tablica z danymi lub nazwa ksiêgarni
     * @param string $ident identyfikator ksi¹¿ki (opcjonalny)
     *
     * @return int
     */
    public function wysokosc_znizki()
    {
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

    /**
     * Zwraca tablicê z danymi na temat ksi¹¿ki.
     *
     * Ta funkcja korzysta z prostego cache'owania, tak wiêc wielokrotne zapytania
     * o tê sam¹ ksi¹¿kê wykonane pod rz¹d bêd¹ obs³ugiwane z cache'u.
     *
     * @param string $ksiegarnia np. "helion", "ebookpoint"
     * @param string $ident np. "grywal", "markwy"
     * @return array
     */
    public function ksiazka($ksiegarnia, $ident)
    {
        if (!$this->val_ksiegarnia($ksiegarnia) || !$this->val_ident($ident)) {
            return false;
        }
        if(!empty($this->cache_ksiazki_{$ksiegarnia}[$ident])) {
            return $this->cache_ksiazki_{$ksiegarnia}[$ident];
        }

        $xml = $this->get_xml('http://' . $ksiegarnia . '.pl/plugins/new/xml/ksiazka.cgi?ident=' . $ident);

        if ($xml) {
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
     *
     * @return string
     */
    public function okladka()
    {
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
            $this->error = 'Nieprawid³owa liczba argumentów.';
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

    public function strip_ident($ident) {

        if (!$this->val_ident($ident)) {
            $this->displayError('Niepoprawny identyfikator ksi±¿ki (ident).');
            return false;
        }

        if (preg_match("/_ebook$/", $ident)) {
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
        if (!preg_match("/^[a-z0-9_]+$/", $ident)) {
            $this->displayError('Niepoprawny identyfikator ksi±¿ki (ident).');
            return false;
        } else {
            return true;
        }
    }

    /**
     * Zwraca aktualny komunikat b³êdu.
     *
     * @return string Komunikat b³êdu
     */
    public function displayError($message) {
        echo $message;
    }

    private function parseDomTop($html)
    {
        $output = array();
        $books = $html->find('div.book-item');

        foreach ($books as $book) {
            $item = array();
            $item['link'] = $book->find('a', 0)->href;
            $positionOfLastComa = strpos($item['link'], ',');
            $item['ident'] = ltrim(substr($item['link'], $positionOfLastComa, -1), ',');
            $explodedIdent = explode('.', $item['ident']);
            $item['ident'] = $explodedIdent[0];

            $item['image'] = str_replace('helion-loader.gif', $item['ident'].'.jpg', $book->find('img', 0)->src);
            $item['title'] = iconv("UTF-8", "ISO-8859-2", $book->find('a', 1)->title);
            $item['price'] = iconv("UTF-8", "ISO-8859-2", ltrim($book->find('table b', 0)->plaintext, 'Cena: '));

            if (strpos($item['title'], 'Kurs video') !== false) {
                //zignoruj video kursy
                continue;
            }
            $output[] = $item;
        }

        return $output;
    }

    private function getDom($url)
    {
        switch ($this->detect_connection_method()) {
            case 'curl':
                return $this->getDomWithCurl($url);
            case 'fopen':
                $this->displayError('unsupported method type');
            default:
                return false;
        }
    }

    private function getDomWithCurl($url)
    {
        return file_get_html($url);
    /*
        if($this->cache_xml[0] == $url)
            return $this->cache_xml[1];

        $cu = curl_init($url);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
        $response = simplexml_load_string(curl_exec($cu));
        curl_close($cu);

        if (!empty($response)) {
            return $response;
        } else {
            $this->displayError('Pobranie danych zakoñczy³o siê niepowodzeniem. Serwer nie zwróci³ danych XML lub dane by³y niepoprawne.');
            return false;
        }*/
    }

    /**
     * Przetwarza obiekt XML na tablicê z informacjami o ksi¹¿ce.
     *
     * @param object $xml
     * @param string $ksiegarnia
     * @return array
     */
    private function parser_xml_ksiazka($xml, $ksiegarnia)
    {
        $a = json_decode(json_encode((array) $xml),1);

        if (is_array($a)) {
            $a['ident'] = strtolower($a['ident']);
            $a['ksiegarnia'] = $ksiegarnia;
            $a['opis'] = (string) $xml->opis;
            return $a;
        } else {
            $this->displayError('Nie uda³o siê przetworzenie danych o ksi¹¿ce do tablicy.');
            return false;
        }
    }

    private function parser_xml_top($xml)
    {
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
            $this->displayError('Nie uda³o siê przetworzenie danych z listy TOP20.');
            return false;
        }
    }

    private function get_xml($url)
    {
        switch($this->detect_connection_method()) {
            case 'curl':
                return $this->get_xml_with_curl($url);
            case 'fopen':
                return $this->get_xml_with_fopen($url);
            default:
                return false;
        }
    }

    private function detect_connection_method()
    {
        if($this->is_curl_enabled()) {
            return 'curl';
        } else if($this->is_allow_url_fopen_enabled()) {
            return 'fopen';
        } else {
            $this->error = '¯adna z metod pobierania danych nie jest dostêpna. Wymagany jest dostêp przez cURL albo przez fopen.';
            return false;
        }
    }

    /**
     *
     * @return bool
     */
    private function is_curl_enabled()
    {
        return (in_array('curl', get_loaded_extensions())) ? true : false;
    }

    /**
     *
     * @return bool
     */
    private function is_allow_url_fopen_enabled()
    {
        return (ini_get('allow_url_fopen') == 1) ? true : false;
    }

    private function get_xml_with_curl($url)
    {
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
            $this->displayError('Pobranie danych XML zakoñczy³o siê niepowodzeniem. Serwer nie zwróci³ danych XML lub dane by³y niepoprawne.');
            return false;
        }
    }

    private function get_xml_with_fopen($url)
    {
        if($this->cache_xml[0] == $url)
            return $this->cache_xml[1];

        $xml = @simplexml_load_file($url);

        if(is_object($xml)) {
            $this->cache_xml[0] = $url;
            $this->cache_xml[1] = $xml;

            return $xml;
        } else {
            $this->displayError('Pobranie danych XML zakoñczy³o siê niepowodzeniem. Serwer nie zwróci³ danych XML lub dane by³y niepoprawne.');
            return false;
        }
    }

    private function match_kategoria($kategoria)
    {
        return preg_match('/^[0-9,]$/', $kategoria);
    }

    private function match_partner($partnerid) {
        return preg_match('/^[0-9]{4}[0-9a-zA-Z.]{1}$/', $partnerid);
    }
}

?>