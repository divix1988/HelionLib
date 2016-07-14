Fork Biblioteki HelionLib dla Programu Partnerskiego

Użycie:
=================

```html
//dołączenie biblioteki
include("libs/helion/helion-lib.php");

$helionLib = new HelionLib('[twoj_ident]', 'helion');
//ustawienie kategori i podkategori dla wyświetlanych wyników
$helionCat = 'webmasterstwo';
$helionPodCat = 'tworzenie-stron-www';
//przekazanie dodatkowych parametrów jak rozmiar obrazka, limit czy losowość
$books = $helionLib->top(
	array(
		'cat' => $helionCat,
		'pod_cat' => $helionPodCat,
		'image_size' => '120x156',
		'limit' => 10,
		'random' => true
	)
);
//zaczynamy budowani listy HTML
$helion = '<ul>';

foreach ($books as $book) {
	$bookLink = $helionLib->link_do_ksiazki('helion', $book['ident']);
	$displayTitle = $book['title'];

	$helion .= '<li><a href="'.$bookLink.'" title="'.$book['title'].'"><img src="'.$book['image'].'"/><span>'.$displayTitle.'</span>'.
		'<span class="price"><b>Cena: '.$book['price'].'</b></span></a></li>';
}
$helion .= '</ul>';

//wyświetlamy ksiązki
echo $helion;
```
