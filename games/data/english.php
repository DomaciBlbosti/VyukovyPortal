<?php
/**
 * Datová sada pro angličtinu — slovíčka.
 *
 * Formát položky: ['en' => 'dog', 'cs' => 'pes']
 * Sady jsou rozdělené podle ročníku: základní okruhy (barvy, čísla,
 * zvířata, rodina, jídlo, škola, tělo) se učí od 3. třídy, pokročilé
 * (slovesa, přídavná jména, povolání, nepravidelná slovesa) od 5.–6.
 *
 * Uvnitř jedné sady se překlady neopakují — nabídka špatných možností
 * se losuje právě z ní, takže by dvě stejné odpovědi hru rozbily.
 */

/** @return array<string, array{label:string, icon:string, grades:array<int>, words:array<array{en:string,cs:string}>}> */
function englishThemes(): array {
    $t = [

    'barvy' => ['label' => 'Barvy', 'icon' => '🎨', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'red',    'cs' => 'červená'],
        ['en' => 'blue',   'cs' => 'modrá'],
        ['en' => 'green',  'cs' => 'zelená'],
        ['en' => 'yellow', 'cs' => 'žlutá'],
        ['en' => 'black',  'cs' => 'černá'],
        ['en' => 'white',  'cs' => 'bílá'],
        ['en' => 'brown',  'cs' => 'hnědá'],
        ['en' => 'orange', 'cs' => 'oranžová'],
        ['en' => 'pink',   'cs' => 'růžová'],
        ['en' => 'purple', 'cs' => 'fialová'],
        ['en' => 'grey',   'cs' => 'šedá'],
        ['en' => 'gold',   'cs' => 'zlatá'],
        ['en' => 'silver', 'cs' => 'stříbrná'],
        ['en' => 'colour', 'cs' => 'barva'],
    ]],

    'cisla' => ['label' => 'Čísla', 'icon' => '🔢', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'one',       'cs' => 'jedna'],
        ['en' => 'two',       'cs' => 'dva'],
        ['en' => 'three',     'cs' => 'tři'],
        ['en' => 'four',      'cs' => 'čtyři'],
        ['en' => 'five',      'cs' => 'pět'],
        ['en' => 'six',       'cs' => 'šest'],
        ['en' => 'seven',     'cs' => 'sedm'],
        ['en' => 'eight',     'cs' => 'osm'],
        ['en' => 'nine',      'cs' => 'devět'],
        ['en' => 'ten',       'cs' => 'deset'],
        ['en' => 'eleven',    'cs' => 'jedenáct'],
        ['en' => 'twelve',    'cs' => 'dvanáct'],
        ['en' => 'thirteen',  'cs' => 'třináct'],
        ['en' => 'fourteen',  'cs' => 'čtrnáct'],
        ['en' => 'fifteen',   'cs' => 'patnáct'],
        ['en' => 'sixteen',   'cs' => 'šestnáct'],
        ['en' => 'seventeen', 'cs' => 'sedmnáct'],
        ['en' => 'eighteen',  'cs' => 'osmnáct'],
        ['en' => 'nineteen',  'cs' => 'devatenáct'],
        ['en' => 'twenty',    'cs' => 'dvacet'],
        ['en' => 'thirty',    'cs' => 'třicet'],
        ['en' => 'forty',     'cs' => 'čtyřicet'],
        ['en' => 'fifty',     'cs' => 'padesát'],
        ['en' => 'sixty',     'cs' => 'šedesát'],
        ['en' => 'seventy',   'cs' => 'sedmdesát'],
        ['en' => 'eighty',    'cs' => 'osmdesát'],
        ['en' => 'ninety',    'cs' => 'devadesát'],
        ['en' => 'hundred',   'cs' => 'sto'],
        ['en' => 'thousand',  'cs' => 'tisíc'],
        ['en' => 'first',     'cs' => 'první'],
        ['en' => 'second',    'cs' => 'druhý'],
        ['en' => 'third',     'cs' => 'třetí'],
    ]],

    'zvirata' => ['label' => 'Zvířata', 'icon' => '🐘', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'dog',       'cs' => 'pes'],
        ['en' => 'cat',       'cs' => 'kočka'],
        ['en' => 'horse',     'cs' => 'kůň'],
        ['en' => 'cow',       'cs' => 'kráva'],
        ['en' => 'pig',       'cs' => 'prase'],
        ['en' => 'sheep',     'cs' => 'ovce'],
        ['en' => 'goat',      'cs' => 'koza'],
        ['en' => 'rabbit',    'cs' => 'králík'],
        ['en' => 'mouse',     'cs' => 'myš'],
        ['en' => 'bird',      'cs' => 'pták'],
        ['en' => 'fish',      'cs' => 'ryba'],
        ['en' => 'duck',      'cs' => 'kachna'],
        ['en' => 'hen',       'cs' => 'slepice'],
        ['en' => 'rooster',   'cs' => 'kohout'],
        ['en' => 'frog',      'cs' => 'žába'],
        ['en' => 'bear',      'cs' => 'medvěd'],
        ['en' => 'wolf',      'cs' => 'vlk'],
        ['en' => 'fox',       'cs' => 'liška'],
        ['en' => 'lion',      'cs' => 'lev'],
        ['en' => 'tiger',     'cs' => 'tygr'],
        ['en' => 'elephant',  'cs' => 'slon'],
        ['en' => 'monkey',    'cs' => 'opice'],
        ['en' => 'snake',     'cs' => 'had'],
        ['en' => 'spider',    'cs' => 'pavouk'],
        ['en' => 'bee',       'cs' => 'včela'],
        ['en' => 'butterfly', 'cs' => 'motýl'],
        ['en' => 'ant',       'cs' => 'mravenec'],
        ['en' => 'squirrel',  'cs' => 'veverka'],
        ['en' => 'deer',      'cs' => 'jelen'],
        ['en' => 'hedgehog',  'cs' => 'ježek'],
        ['en' => 'turtle',    'cs' => 'želva'],
        ['en' => 'whale',     'cs' => 'velryba'],
        ['en' => 'shark',     'cs' => 'žralok'],
        ['en' => 'giraffe',   'cs' => 'žirafa'],
        ['en' => 'penguin',   'cs' => 'tučňák'],
        ['en' => 'owl',       'cs' => 'sova'],
    ]],

    'rodina' => ['label' => 'Rodina a lidé', 'icon' => '👨‍👩‍👧', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'mother',      'cs' => 'matka'],
        ['en' => 'father',      'cs' => 'otec'],
        ['en' => 'sister',      'cs' => 'sestra'],
        ['en' => 'brother',     'cs' => 'bratr'],
        ['en' => 'grandmother', 'cs' => 'babička'],
        ['en' => 'grandfather', 'cs' => 'dědeček'],
        ['en' => 'son',         'cs' => 'syn'],
        ['en' => 'daughter',    'cs' => 'dcera'],
        ['en' => 'aunt',        'cs' => 'teta'],
        ['en' => 'uncle',       'cs' => 'strýc'],
        ['en' => 'cousin',      'cs' => 'bratranec'],
        ['en' => 'parents',     'cs' => 'rodiče'],
        ['en' => 'baby',        'cs' => 'miminko'],
        ['en' => 'child',       'cs' => 'dítě'],
        ['en' => 'children',    'cs' => 'děti'],
        ['en' => 'family',      'cs' => 'rodina'],
        ['en' => 'friend',      'cs' => 'kamarád'],
        ['en' => 'boy',         'cs' => 'chlapec'],
        ['en' => 'girl',        'cs' => 'dívka'],
        ['en' => 'man',         'cs' => 'muž'],
        ['en' => 'woman',       'cs' => 'žena'],
        ['en' => 'wife',        'cs' => 'manželka'],
        ['en' => 'husband',     'cs' => 'manžel'],
        ['en' => 'neighbour',   'cs' => 'soused'],
        ['en' => 'people',      'cs' => 'lidé'],
        ['en' => 'name',        'cs' => 'jméno'],
    ]],

    'jidlo' => ['label' => 'Jídlo a pití', 'icon' => '🍎', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'bread',      'cs' => 'chléb'],
        ['en' => 'butter',     'cs' => 'máslo'],
        ['en' => 'cheese',     'cs' => 'sýr'],
        ['en' => 'milk',       'cs' => 'mléko'],
        ['en' => 'water',      'cs' => 'voda'],
        ['en' => 'juice',      'cs' => 'džus'],
        ['en' => 'tea',        'cs' => 'čaj'],
        ['en' => 'coffee',     'cs' => 'káva'],
        ['en' => 'egg',        'cs' => 'vejce'],
        ['en' => 'meat',       'cs' => 'maso'],
        ['en' => 'chicken',    'cs' => 'kuře'],
        ['en' => 'soup',       'cs' => 'polévka'],
        ['en' => 'rice',       'cs' => 'rýže'],
        ['en' => 'potato',     'cs' => 'brambora'],
        ['en' => 'tomato',     'cs' => 'rajče'],
        ['en' => 'carrot',     'cs' => 'mrkev'],
        ['en' => 'onion',      'cs' => 'cibule'],
        ['en' => 'apple',      'cs' => 'jablko'],
        ['en' => 'pear',       'cs' => 'hruška'],
        ['en' => 'banana',     'cs' => 'banán'],
        ['en' => 'orange',     'cs' => 'pomeranč'],
        ['en' => 'strawberry', 'cs' => 'jahoda'],
        ['en' => 'grapes',     'cs' => 'hroznové víno'],
        ['en' => 'cake',       'cs' => 'dort'],
        ['en' => 'chocolate',  'cs' => 'čokoláda'],
        ['en' => 'sugar',      'cs' => 'cukr'],
        ['en' => 'salt',       'cs' => 'sůl'],
        ['en' => 'honey',      'cs' => 'med'],
        ['en' => 'ice cream',  'cs' => 'zmrzlina'],
        ['en' => 'breakfast',  'cs' => 'snídaně'],
        ['en' => 'lunch',      'cs' => 'oběd'],
        ['en' => 'dinner',     'cs' => 'večeře'],
        ['en' => 'plate',      'cs' => 'talíř'],
        ['en' => 'cup',        'cs' => 'hrnek'],
        ['en' => 'spoon',      'cs' => 'lžíce'],
        ['en' => 'knife',      'cs' => 'nůž'],
        ['en' => 'fork',       'cs' => 'vidlička'],
    ]],

    'skola' => ['label' => 'Škola', 'icon' => '🎒', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'school',     'cs' => 'škola'],
        ['en' => 'teacher',    'cs' => 'učitel'],
        ['en' => 'pupil',      'cs' => 'žák'],
        ['en' => 'classroom',  'cs' => 'třída'],
        ['en' => 'desk',       'cs' => 'lavice'],
        ['en' => 'chair',      'cs' => 'židle'],
        ['en' => 'blackboard', 'cs' => 'tabule'],
        ['en' => 'book',       'cs' => 'kniha'],
        ['en' => 'notebook',   'cs' => 'sešit'],
        ['en' => 'pen',        'cs' => 'pero'],
        ['en' => 'pencil',     'cs' => 'tužka'],
        ['en' => 'rubber',     'cs' => 'guma'],
        ['en' => 'ruler',      'cs' => 'pravítko'],
        ['en' => 'bag',        'cs' => 'taška'],
        ['en' => 'scissors',   'cs' => 'nůžky'],
        ['en' => 'glue',       'cs' => 'lepidlo'],
        ['en' => 'homework',   'cs' => 'domácí úkol'],
        ['en' => 'lesson',     'cs' => 'vyučovací hodina'],
        ['en' => 'break',      'cs' => 'přestávka'],
        ['en' => 'test',       'cs' => 'písemka'],
        ['en' => 'mark',       'cs' => 'známka'],
        ['en' => 'English',    'cs' => 'angličtina'],
        ['en' => 'maths',      'cs' => 'matematika'],
        ['en' => 'dictionary', 'cs' => 'slovník'],
        ['en' => 'page',       'cs' => 'stránka'],
        ['en' => 'word',       'cs' => 'slovo'],
        ['en' => 'question',   'cs' => 'otázka'],
        ['en' => 'answer',     'cs' => 'odpověď'],
        ['en' => 'timetable',  'cs' => 'rozvrh'],
        ['en' => 'holidays',   'cs' => 'prázdniny'],
    ]],

    'telo' => ['label' => 'Lidské tělo', 'icon' => '🖐', 'grades' => [3,4,5,6,7,8,9], 'words' => [
        ['en' => 'head',     'cs' => 'hlava'],
        ['en' => 'hair',     'cs' => 'vlasy'],
        ['en' => 'face',     'cs' => 'obličej'],
        ['en' => 'eye',      'cs' => 'oko'],
        ['en' => 'ear',      'cs' => 'ucho'],
        ['en' => 'nose',     'cs' => 'nos'],
        ['en' => 'mouth',    'cs' => 'ústa'],
        ['en' => 'tooth',    'cs' => 'zub'],
        ['en' => 'tongue',   'cs' => 'jazyk'],
        ['en' => 'neck',     'cs' => 'krk'],
        ['en' => 'shoulder', 'cs' => 'rameno'],
        ['en' => 'arm',      'cs' => 'paže'],
        ['en' => 'hand',     'cs' => 'ruka'],
        ['en' => 'finger',   'cs' => 'prst'],
        ['en' => 'leg',      'cs' => 'noha'],
        ['en' => 'knee',     'cs' => 'koleno'],
        ['en' => 'foot',     'cs' => 'chodidlo'],
        ['en' => 'back',     'cs' => 'záda'],
        ['en' => 'stomach',  'cs' => 'břicho'],
        ['en' => 'heart',    'cs' => 'srdce'],
        ['en' => 'bone',     'cs' => 'kost'],
        ['en' => 'skin',     'cs' => 'kůže'],
        ['en' => 'blood',    'cs' => 'krev'],
        ['en' => 'brain',    'cs' => 'mozek'],
        ['en' => 'finger nail', 'cs' => 'nehet'],
    ]],

    'dum' => ['label' => 'Dům a bydlení', 'icon' => '🏠', 'grades' => [4,5,6,7,8,9], 'words' => [
        ['en' => 'house',       'cs' => 'dům'],
        ['en' => 'flat',        'cs' => 'byt (bydlení)'],
        ['en' => 'room',        'cs' => 'pokoj'],
        ['en' => 'kitchen',     'cs' => 'kuchyně'],
        ['en' => 'bathroom',    'cs' => 'koupelna'],
        ['en' => 'bedroom',     'cs' => 'ložnice'],
        ['en' => 'living room', 'cs' => 'obývací pokoj'],
        ['en' => 'hall',        'cs' => 'předsíň'],
        ['en' => 'garden',      'cs' => 'zahrada'],
        ['en' => 'garage',      'cs' => 'garáž'],
        ['en' => 'door',        'cs' => 'dveře'],
        ['en' => 'window',      'cs' => 'okno'],
        ['en' => 'wall',        'cs' => 'zeď'],
        ['en' => 'floor',       'cs' => 'podlaha'],
        ['en' => 'ceiling',     'cs' => 'strop'],
        ['en' => 'roof',        'cs' => 'střecha'],
        ['en' => 'stairs',      'cs' => 'schody'],
        ['en' => 'table',       'cs' => 'stůl'],
        ['en' => 'bed',         'cs' => 'postel'],
        ['en' => 'sofa',        'cs' => 'pohovka'],
        ['en' => 'wardrobe',    'cs' => 'skříň'],
        ['en' => 'shelf',       'cs' => 'police'],
        ['en' => 'lamp',        'cs' => 'lampa'],
        ['en' => 'mirror',      'cs' => 'zrcadlo'],
        ['en' => 'carpet',      'cs' => 'koberec'],
        ['en' => 'fridge',      'cs' => 'lednice'],
        ['en' => 'cooker',      'cs' => 'sporák'],
        ['en' => 'sink',        'cs' => 'dřez'],
        ['en' => 'towel',       'cs' => 'ručník'],
        ['en' => 'key',         'cs' => 'klíč'],
        ['en' => 'soap',        'cs' => 'mýdlo'],
    ]],

    'obleceni' => ['label' => 'Oblečení', 'icon' => '👕', 'grades' => [4,5,6,7,8,9], 'words' => [
        ['en' => 'shirt',    'cs' => 'košile'],
        ['en' => 'T-shirt',  'cs' => 'tričko'],
        ['en' => 'trousers', 'cs' => 'kalhoty'],
        ['en' => 'jeans',    'cs' => 'džíny'],
        ['en' => 'skirt',    'cs' => 'sukně'],
        ['en' => 'dress',    'cs' => 'šaty'],
        ['en' => 'jumper',   'cs' => 'svetr'],
        ['en' => 'jacket',   'cs' => 'bunda'],
        ['en' => 'coat',     'cs' => 'kabát'],
        ['en' => 'shoes',    'cs' => 'boty'],
        ['en' => 'socks',    'cs' => 'ponožky'],
        ['en' => 'boots',    'cs' => 'holínky'],
        ['en' => 'hat',      'cs' => 'klobouk'],
        ['en' => 'cap',      'cs' => 'čepice'],
        ['en' => 'scarf',    'cs' => 'šála'],
        ['en' => 'gloves',   'cs' => 'rukavice'],
        ['en' => 'belt',     'cs' => 'pásek'],
        ['en' => 'pocket',   'cs' => 'kapsa'],
        ['en' => 'button',   'cs' => 'knoflík'],
        ['en' => 'tie',      'cs' => 'kravata'],
        ['en' => 'pyjamas',  'cs' => 'pyžamo'],
        ['en' => 'swimsuit', 'cs' => 'plavky'],
        ['en' => 'glasses',  'cs' => 'brýle'],
        ['en' => 'watch',    'cs' => 'hodinky'],
        ['en' => 'ring',     'cs' => 'prsten'],
        ['en' => 'umbrella', 'cs' => 'deštník'],
    ]],

    'cas' => ['label' => 'Čas, dny a měsíce', 'icon' => '📅', 'grades' => [4,5,6,7,8,9], 'words' => [
        ['en' => 'Monday',    'cs' => 'pondělí'],
        ['en' => 'Tuesday',   'cs' => 'úterý'],
        ['en' => 'Wednesday', 'cs' => 'středa'],
        ['en' => 'Thursday',  'cs' => 'čtvrtek'],
        ['en' => 'Friday',    'cs' => 'pátek'],
        ['en' => 'Saturday',  'cs' => 'sobota'],
        ['en' => 'Sunday',    'cs' => 'neděle'],
        ['en' => 'January',   'cs' => 'leden'],
        ['en' => 'February',  'cs' => 'únor'],
        ['en' => 'March',     'cs' => 'březen'],
        ['en' => 'April',     'cs' => 'duben'],
        ['en' => 'May',       'cs' => 'květen'],
        ['en' => 'June',      'cs' => 'červen'],
        ['en' => 'July',      'cs' => 'červenec'],
        ['en' => 'August',    'cs' => 'srpen'],
        ['en' => 'September', 'cs' => 'září'],
        ['en' => 'October',   'cs' => 'říjen'],
        ['en' => 'November',  'cs' => 'listopad'],
        ['en' => 'December',  'cs' => 'prosinec'],
        ['en' => 'day',       'cs' => 'den'],
        ['en' => 'week',      'cs' => 'týden'],
        ['en' => 'month',     'cs' => 'měsíc'],
        ['en' => 'year',      'cs' => 'rok'],
        ['en' => 'today',     'cs' => 'dnes'],
        ['en' => 'tomorrow',  'cs' => 'zítra'],
        ['en' => 'yesterday', 'cs' => 'včera'],
        ['en' => 'morning',   'cs' => 'ráno'],
        ['en' => 'afternoon', 'cs' => 'odpoledne'],
        ['en' => 'evening',   'cs' => 'večer'],
        ['en' => 'night',     'cs' => 'noc'],
        ['en' => 'hour',      'cs' => 'hodina'],
        ['en' => 'minute',    'cs' => 'minuta'],
        ['en' => 'spring',    'cs' => 'jaro'],
        ['en' => 'summer',    'cs' => 'léto'],
        ['en' => 'autumn',    'cs' => 'podzim'],
        ['en' => 'winter',    'cs' => 'zima'],
        ['en' => 'weekend',   'cs' => 'víkend'],
        ['en' => 'birthday',  'cs' => 'narozeniny'],
        ['en' => 'clock',     'cs' => 'hodiny'],
    ]],

    'priroda' => ['label' => 'Příroda a počasí', 'icon' => '🌦', 'grades' => [4,5,6,7,8,9], 'words' => [
        ['en' => 'weather',  'cs' => 'počasí'],
        ['en' => 'sun',      'cs' => 'slunce'],
        ['en' => 'rain',     'cs' => 'déšť'],
        ['en' => 'snow',     'cs' => 'sníh'],
        ['en' => 'wind',     'cs' => 'vítr'],
        ['en' => 'cloud',    'cs' => 'mrak'],
        ['en' => 'storm',    'cs' => 'bouřka'],
        ['en' => 'fog',      'cs' => 'mlha'],
        ['en' => 'ice',      'cs' => 'led'],
        ['en' => 'sky',      'cs' => 'obloha'],
        ['en' => 'rainbow',  'cs' => 'duha'],
        ['en' => 'star',     'cs' => 'hvězda'],
        ['en' => 'moon',     'cs' => 'měsíc na obloze'],
        ['en' => 'tree',     'cs' => 'strom'],
        ['en' => 'flower',   'cs' => 'květina'],
        ['en' => 'grass',    'cs' => 'tráva'],
        ['en' => 'leaf',     'cs' => 'list'],
        ['en' => 'forest',   'cs' => 'les'],
        ['en' => 'mountain', 'cs' => 'hora'],
        ['en' => 'hill',     'cs' => 'kopec'],
        ['en' => 'river',    'cs' => 'řeka'],
        ['en' => 'lake',     'cs' => 'jezero'],
        ['en' => 'sea',      'cs' => 'moře'],
        ['en' => 'island',   'cs' => 'ostrov'],
        ['en' => 'field',    'cs' => 'pole'],
        ['en' => 'stone',    'cs' => 'kámen'],
        ['en' => 'sand',     'cs' => 'písek'],
        ['en' => 'beach',    'cs' => 'pláž'],
        ['en' => 'fire',     'cs' => 'oheň'],
        ['en' => 'earth',    'cs' => 'země'],
    ]],

    'slovesa' => ['label' => 'Slovesa', 'icon' => '🏃', 'grades' => [5,6,7,8,9], 'words' => [
        ['en' => 'be',      'cs' => 'být'],
        ['en' => 'have',    'cs' => 'mít'],
        ['en' => 'do',      'cs' => 'dělat'],
        ['en' => 'go',      'cs' => 'jít'],
        ['en' => 'come',    'cs' => 'přijít'],
        ['en' => 'eat',     'cs' => 'jíst'],
        ['en' => 'drink',   'cs' => 'pít'],
        ['en' => 'sleep',   'cs' => 'spát'],
        ['en' => 'run',     'cs' => 'běžet'],
        ['en' => 'walk',    'cs' => 'chodit'],
        ['en' => 'swim',    'cs' => 'plavat'],
        ['en' => 'jump',    'cs' => 'skákat'],
        ['en' => 'fly',     'cs' => 'létat'],
        ['en' => 'read',    'cs' => 'číst'],
        ['en' => 'write',   'cs' => 'psát'],
        ['en' => 'speak',   'cs' => 'mluvit'],
        ['en' => 'listen',  'cs' => 'poslouchat'],
        ['en' => 'watch',   'cs' => 'dívat se'],
        ['en' => 'see',     'cs' => 'vidět'],
        ['en' => 'hear',    'cs' => 'slyšet'],
        ['en' => 'think',   'cs' => 'myslet'],
        ['en' => 'know',    'cs' => 'vědět'],
        ['en' => 'learn',   'cs' => 'učit se'],
        ['en' => 'teach',   'cs' => 'učit někoho'],
        ['en' => 'help',    'cs' => 'pomáhat'],
        ['en' => 'work',    'cs' => 'pracovat'],
        ['en' => 'play',    'cs' => 'hrát si'],
        ['en' => 'sing',    'cs' => 'zpívat'],
        ['en' => 'dance',   'cs' => 'tancovat'],
        ['en' => 'draw',    'cs' => 'kreslit'],
        ['en' => 'buy',     'cs' => 'koupit'],
        ['en' => 'sell',    'cs' => 'prodat'],
        ['en' => 'open',    'cs' => 'otevřít'],
        ['en' => 'close',   'cs' => 'zavřít'],
        ['en' => 'give',    'cs' => 'dát'],
        ['en' => 'take',    'cs' => 'vzít'],
        ['en' => 'find',    'cs' => 'najít'],
        ['en' => 'lose',    'cs' => 'ztratit'],
        ['en' => 'ask',     'cs' => 'ptát se'],
        ['en' => 'answer',  'cs' => 'odpovědět'],
        ['en' => 'wait',    'cs' => 'čekat'],
        ['en' => 'live',    'cs' => 'žít'],
        ['en' => 'love',    'cs' => 'milovat'],
        ['en' => 'like',    'cs' => 'mít rád'],
        ['en' => 'want',    'cs' => 'chtít'],
        ['en' => 'need',    'cs' => 'potřebovat'],
        ['en' => 'cook',    'cs' => 'vařit'],
        ['en' => 'clean',   'cs' => 'uklízet'],
        ['en' => 'drive',   'cs' => 'řídit'],
        ['en' => 'travel',  'cs' => 'cestovat'],
    ]],

    'pridavna' => ['label' => 'Přídavná jména', 'icon' => '↔️', 'grades' => [5,6,7,8,9], 'words' => [
        ['en' => 'big',       'cs' => 'velký'],
        ['en' => 'small',     'cs' => 'malý'],
        ['en' => 'long',      'cs' => 'dlouhý'],
        ['en' => 'short',     'cs' => 'krátký'],
        ['en' => 'tall',      'cs' => 'vysoký'],
        ['en' => 'old',       'cs' => 'starý'],
        ['en' => 'new',       'cs' => 'nový'],
        ['en' => 'young',     'cs' => 'mladý'],
        ['en' => 'good',      'cs' => 'dobrý'],
        ['en' => 'bad',       'cs' => 'špatný'],
        ['en' => 'happy',     'cs' => 'šťastný'],
        ['en' => 'sad',       'cs' => 'smutný'],
        ['en' => 'fast',      'cs' => 'rychlý'],
        ['en' => 'slow',      'cs' => 'pomalý'],
        ['en' => 'easy',      'cs' => 'snadný'],
        ['en' => 'difficult', 'cs' => 'obtížný'],
        ['en' => 'strong',    'cs' => 'silný'],
        ['en' => 'weak',      'cs' => 'slabý'],
        ['en' => 'clean',     'cs' => 'čistý'],
        ['en' => 'dirty',     'cs' => 'špinavý'],
        ['en' => 'full',      'cs' => 'plný'],
        ['en' => 'empty',     'cs' => 'prázdný'],
        ['en' => 'cheap',     'cs' => 'levný'],
        ['en' => 'expensive', 'cs' => 'drahý'],
        ['en' => 'beautiful', 'cs' => 'krásný'],
        ['en' => 'ugly',      'cs' => 'ošklivý'],
        ['en' => 'funny',     'cs' => 'vtipný'],
        ['en' => 'angry',     'cs' => 'naštvaný'],
        ['en' => 'tired',     'cs' => 'unavený'],
        ['en' => 'hungry',    'cs' => 'hladový'],
        ['en' => 'thirsty',   'cs' => 'žíznivý'],
        ['en' => 'quiet',     'cs' => 'tichý'],
        ['en' => 'loud',      'cs' => 'hlasitý'],
        ['en' => 'dangerous', 'cs' => 'nebezpečný'],
        ['en' => 'safe',      'cs' => 'bezpečný'],
        ['en' => 'important', 'cs' => 'důležitý'],
        ['en' => 'hot',       'cs' => 'horký'],
        ['en' => 'cold',      'cs' => 'studený'],
        ['en' => 'warm',      'cs' => 'teplý'],
        ['en' => 'wet',       'cs' => 'mokrý'],
        ['en' => 'dry',       'cs' => 'suchý'],
        ['en' => 'heavy',     'cs' => 'těžký (váha)'],
        ['en' => 'light',     'cs' => 'lehký'],
    ]],

    'sport' => ['label' => 'Sport a koníčky', 'icon' => '⚽', 'grades' => [5,6,7,8,9], 'words' => [
        ['en' => 'sport',      'cs' => 'sport'],
        ['en' => 'football',   'cs' => 'fotbal'],
        ['en' => 'basketball', 'cs' => 'basketbal'],
        ['en' => 'volleyball', 'cs' => 'volejbal'],
        ['en' => 'tennis',     'cs' => 'tenis'],
        ['en' => 'ice hockey', 'cs' => 'lední hokej'],
        ['en' => 'swimming',   'cs' => 'plavání'],
        ['en' => 'running',    'cs' => 'běh'],
        ['en' => 'cycling',    'cs' => 'cyklistika'],
        ['en' => 'skiing',     'cs' => 'lyžování'],
        ['en' => 'skating',    'cs' => 'bruslení'],
        ['en' => 'climbing',   'cs' => 'lezení'],
        ['en' => 'dancing',    'cs' => 'tanec'],
        ['en' => 'ball',       'cs' => 'míč'],
        ['en' => 'team',       'cs' => 'tým'],
        ['en' => 'player',     'cs' => 'hráč'],
        ['en' => 'game',       'cs' => 'hra'],
        ['en' => 'match',      'cs' => 'zápas'],
        ['en' => 'goal',       'cs' => 'gól'],
        ['en' => 'coach',      'cs' => 'trenér'],
        ['en' => 'gym',        'cs' => 'tělocvična'],
        ['en' => 'winner',     'cs' => 'vítěz'],
        ['en' => 'music',      'cs' => 'hudba'],
        ['en' => 'guitar',     'cs' => 'kytara'],
        ['en' => 'piano',      'cs' => 'klavír'],
        ['en' => 'song',       'cs' => 'píseň'],
        ['en' => 'film',       'cs' => 'film'],
        ['en' => 'photo',      'cs' => 'fotka'],
        ['en' => 'computer',   'cs' => 'počítač'],
        ['en' => 'chess',      'cs' => 'šachy'],
        ['en' => 'hobby',      'cs' => 'koníček'],
        ['en' => 'reading',    'cs' => 'čtení'],
    ]],

    'povolani' => ['label' => 'Povolání', 'icon' => '👩‍🚒', 'grades' => [6,7,8,9], 'words' => [
        ['en' => 'doctor',      'cs' => 'lékař'],
        ['en' => 'nurse',       'cs' => 'zdravotní sestra'],
        ['en' => 'teacher',     'cs' => 'učitel'],
        ['en' => 'policeman',   'cs' => 'policista'],
        ['en' => 'firefighter', 'cs' => 'hasič'],
        ['en' => 'driver',      'cs' => 'řidič'],
        ['en' => 'pilot',       'cs' => 'pilot'],
        ['en' => 'cook',        'cs' => 'kuchař'],
        ['en' => 'waiter',      'cs' => 'číšník'],
        ['en' => 'shop assistant', 'cs' => 'prodavač'],
        ['en' => 'farmer',      'cs' => 'farmář'],
        ['en' => 'engineer',    'cs' => 'inženýr'],
        ['en' => 'scientist',   'cs' => 'vědec'],
        ['en' => 'lawyer',      'cs' => 'právník'],
        ['en' => 'journalist',  'cs' => 'novinář'],
        ['en' => 'writer',      'cs' => 'spisovatel'],
        ['en' => 'painter',     'cs' => 'malíř'],
        ['en' => 'singer',      'cs' => 'zpěvák'],
        ['en' => 'actor',       'cs' => 'herec'],
        ['en' => 'dentist',     'cs' => 'zubař'],
        ['en' => 'vet',         'cs' => 'veterinář'],
        ['en' => 'hairdresser', 'cs' => 'kadeřník'],
        ['en' => 'builder',     'cs' => 'stavitel'],
        ['en' => 'mechanic',    'cs' => 'mechanik'],
        ['en' => 'postman',     'cs' => 'pošťák'],
        ['en' => 'soldier',     'cs' => 'voják'],
        ['en' => 'manager',     'cs' => 'manažer'],
        ['en' => 'student',     'cs' => 'student'],
        ['en' => 'job',         'cs' => 'zaměstnání'],
    ]],

    'mesto' => ['label' => 'Město a cestování', 'icon' => '🚂', 'grades' => [6,7,8,9], 'words' => [
        ['en' => 'town',        'cs' => 'město'],
        ['en' => 'village',     'cs' => 'vesnice'],
        ['en' => 'street',      'cs' => 'ulice'],
        ['en' => 'square',      'cs' => 'náměstí'],
        ['en' => 'bridge',      'cs' => 'most'],
        ['en' => 'park',        'cs' => 'park'],
        ['en' => 'shop',        'cs' => 'obchod'],
        ['en' => 'market',      'cs' => 'trh'],
        ['en' => 'bank',        'cs' => 'banka'],
        ['en' => 'post office', 'cs' => 'pošta'],
        ['en' => 'hospital',    'cs' => 'nemocnice'],
        ['en' => 'church',      'cs' => 'kostel'],
        ['en' => 'castle',      'cs' => 'hrad'],
        ['en' => 'museum',      'cs' => 'muzeum'],
        ['en' => 'library',     'cs' => 'knihovna'],
        ['en' => 'cinema',      'cs' => 'kino'],
        ['en' => 'theatre',     'cs' => 'divadlo'],
        ['en' => 'restaurant',  'cs' => 'restaurace'],
        ['en' => 'hotel',       'cs' => 'hotel'],
        ['en' => 'airport',     'cs' => 'letiště'],
        ['en' => 'station',     'cs' => 'nádraží'],
        ['en' => 'bus',         'cs' => 'autobus'],
        ['en' => 'train',       'cs' => 'vlak'],
        ['en' => 'car',         'cs' => 'auto'],
        ['en' => 'plane',       'cs' => 'letadlo'],
        ['en' => 'ship',        'cs' => 'loď'],
        ['en' => 'bike',        'cs' => 'kolo'],
        ['en' => 'ticket',      'cs' => 'jízdenka'],
        ['en' => 'map',         'cs' => 'mapa'],
        ['en' => 'luggage',     'cs' => 'zavazadlo'],
        ['en' => 'passport',    'cs' => 'pas'],
        ['en' => 'holiday',     'cs' => 'dovolená'],
        ['en' => 'road',        'cs' => 'silnice'],
        ['en' => 'money',       'cs' => 'peníze'],
        ['en' => 'country',     'cs' => 'stát'],
        ['en' => 'language',    'cs' => 'jazyk (řeč)'],
    ]],

    'nepravidelna' => ['label' => 'Nepravidelná slovesa', 'icon' => '⏪', 'grades' => [6,7,8,9], 'words' => [
        ['en' => 'went',        'cs' => 'jít (go)'],
        ['en' => 'saw',         'cs' => 'vidět (see)'],
        ['en' => 'ate',         'cs' => 'jíst (eat)'],
        ['en' => 'drank',       'cs' => 'pít (drink)'],
        ['en' => 'came',        'cs' => 'přijít (come)'],
        ['en' => 'took',        'cs' => 'vzít (take)'],
        ['en' => 'gave',        'cs' => 'dát (give)'],
        ['en' => 'made',        'cs' => 'udělat (make)'],
        ['en' => 'did',         'cs' => 'dělat (do)'],
        ['en' => 'had',         'cs' => 'mít (have)'],
        ['en' => 'was',         'cs' => 'byl (be)'],
        ['en' => 'said',        'cs' => 'říct (say)'],
        ['en' => 'got',         'cs' => 'dostat (get)'],
        ['en' => 'knew',        'cs' => 'vědět (know)'],
        ['en' => 'thought',     'cs' => 'myslet (think)'],
        ['en' => 'wrote',       'cs' => 'psát (write)'],
        ['en' => 'spoke',       'cs' => 'mluvit (speak)'],
        ['en' => 'ran',         'cs' => 'běžet (run)'],
        ['en' => 'swam',        'cs' => 'plavat (swim)'],
        ['en' => 'flew',        'cs' => 'létat (fly)'],
        ['en' => 'bought',      'cs' => 'koupit (buy)'],
        ['en' => 'brought',     'cs' => 'přinést (bring)'],
        ['en' => 'taught',      'cs' => 'učit (teach)'],
        ['en' => 'caught',      'cs' => 'chytit (catch)'],
        ['en' => 'found',       'cs' => 'najít (find)'],
        ['en' => 'lost',        'cs' => 'ztratit (lose)'],
        ['en' => 'slept',       'cs' => 'spát (sleep)'],
        ['en' => 'sang',        'cs' => 'zpívat (sing)'],
        ['en' => 'sat',         'cs' => 'sedět (sit)'],
        ['en' => 'stood',       'cs' => 'stát (stand)'],
        ['en' => 'won',         'cs' => 'vyhrát (win)'],
        ['en' => 'began',       'cs' => 'začít (begin)'],
        ['en' => 'broke',       'cs' => 'rozbít (break)'],
        ['en' => 'chose',       'cs' => 'vybrat (choose)'],
        ['en' => 'drove',       'cs' => 'řídit (drive)'],
        ['en' => 'fell',        'cs' => 'spadnout (fall)'],
        ['en' => 'felt',        'cs' => 'cítit (feel)'],
        ['en' => 'left',        'cs' => 'odejít (leave)'],
        ['en' => 'met',         'cs' => 'potkat (meet)'],
        ['en' => 'paid',        'cs' => 'zaplatit (pay)'],
        ['en' => 'sent',        'cs' => 'poslat (send)'],
        ['en' => 'told',        'cs' => 'povědět (tell)'],
        ['en' => 'wore',        'cs' => 'nosit (wear)'],
        ['en' => 'understood',  'cs' => 'rozumět (understand)'],
    ]],

    ];

    // Míchaná sada ze všech okruhů kromě nepravidelných sloves — ta mají
    // jiný formát zadání a v mixu by mátla.
    $mix = [];
    foreach ($t as $key => $theme) {
        if ($key === 'nepravidelna') continue;
        foreach ($theme['words'] as $w) $mix[] = $w;
    }
    $t['vse'] = ['label' => 'Vše dohromady', 'icon' => '🎲', 'grades' => [4,5,6,7,8,9], 'words' => $mix];

    return $t;
}

/** Sady dostupné pro daný ročník (0 = neuvedeno → všechny) */
function englishThemesForGrade(int $grade): array {
    $all = englishThemes();
    if ($grade < 1) return $all;
    return array_filter($all, fn($t) => in_array($grade, $t['grades'], true));
}

/**
 * Porovnávací tvar odpovědi — malá písmena, bez diakritiky, bez
 * dvojitých mezer. Stejný postup má i JS, aby se výsledky shodovaly.
 */
function englishNorm(string $s): string {
    $map = ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r',
            'š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z'];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9 ]+/u', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Tvary, které při psaní uznáme za správné: celá odpověď a varianty bez
 * upřesnění v závorce nebo za pomlčkou ("země (stát)" → i "země").
 */
function englishAccept(string $answer): array {
    $forms = [$answer];
    $forms[] = preg_replace('/\s*\(.*?\)\s*/u', ' ', $answer);
    $forms[] = preg_replace('/\s*—.*$/u', '', $answer);
    if (str_starts_with($answer, 'to ')) $forms[] = substr($answer, 3);
    $out = [];
    foreach ($forms as $f) {
        $n = englishNorm($f);
        if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
    }
    return $out;
}

/**
 * Sestaví kolo úloh.
 *
 * @param string $dir 'cs_en' = česky se ptáme, anglicky odpovídáme; 'en_cs' opačně
 * @return array<array{q:string,a:string,accept:array,options:array,hint:string}>
 */
function englishTasks(string $themeKey, int $count = 12, string $dir = 'cs_en', int $optionCount = 4): array {
    $themes = englishThemes();
    if (!isset($themes[$themeKey])) return [];
    $words = $themes[$themeKey]['words'];

    [$qKey, $aKey] = $dir === 'en_cs' ? ['en', 'cs'] : ['cs', 'en'];

    // Zadání i odpovědi musí být v rámci sady jedinečné. Kdyby se opakovala
    // odpověď, byla by v nabídce dvakrát správná možnost; kdyby se opakovalo
    // zadání (v mixu třeba "orange" = oranžová i pomeranč), měla by otázka
    // dvě správná řešení a dítě by ji nemohlo uhodnout.
    $seenQ = $seenA = [];
    $pool  = [];
    foreach ($words as $w) {
        $q = englishNorm($w[$qKey]);
        $a = englishNorm($w[$aKey]);
        if ($q === '' || $a === '' || isset($seenQ[$q]) || isset($seenA[$a])) continue;
        $seenQ[$q] = $seenA[$a] = true;
        $pool[]    = $w;
    }
    if (count($pool) < 2) return [];

    $picked = $pool;
    shuffle($picked);
    $picked = array_slice($picked, 0, min($count, count($picked)));

    $tasks = [];
    foreach ($picked as $w) {
        $tasks[] = englishBuildTask($w, $pool, $qKey, $aKey, $dir, $optionCount);
    }
    return $tasks;
}

/** Sestaví jednu úlohu včetně nabídky špatných možností ze stejného okruhu */
function englishBuildTask(array $w, array $pool, string $qKey, string $aKey,
                          string $dir, int $optionCount = 4): array {
    $answer = $w[$aKey];

    $distractors = [];
    foreach ($pool as $other) {
        if (englishNorm($other[$aKey]) !== englishNorm($answer)) $distractors[] = $other[$aKey];
    }
    shuffle($distractors);
    $options = array_merge([$answer], array_slice($distractors, 0, max(1, $optionCount - 1)));
    shuffle($options);

    return [
        // Klíč pro chybovník nese i směr — „dog → pes" a „pes → dog"
        // jsou pro dítě dvě různé dovednosti
        'key'     => $dir . ':' . $w['en'],
        'q'       => $w[$qKey],
        'a'       => $answer,
        'accept'  => englishAccept($answer),
        'options' => array_values($options),
        'hint'    => $w['en'] . ' = ' . $w['cs'],
    ];
}

/**
 * Vymění v kole náhodné úlohy za ty, které dítě naposled splétlo.
 *
 * @param array $tasks kolo, mění se na místě
 * @param array $keys  klíče z chybovníku (ve tvaru „směr:slovo")
 * @return int kolik úloh se povedlo vyměnit
 */
function englishInjectPractice(array &$tasks, array $keys, string $themeKey, string $dir): int {
    if (!$keys || !$tasks) return 0;

    $themes = englishThemes();
    if (!isset($themes[$themeKey])) return 0;
    [$qKey, $aKey] = $dir === 'en_cs' ? ['en', 'cs'] : ['cs', 'en'];

    // Nabídku špatných možností stavíme ze stejného okruhu jako v kole
    $seenQ = $seenA = [];
    $pool  = [];
    foreach ($themes[$themeKey]['words'] as $w) {
        $q = englishNorm($w[$qKey]);
        $a = englishNorm($w[$aKey]);
        if ($q === '' || $a === '' || isset($seenQ[$q]) || isset($seenA[$a])) continue;
        $seenQ[$q] = $seenA[$a] = true;
        $pool[]    = $w;
    }
    $byKey = array_column($pool, null, 'en');

    $done = 0;
    foreach ($keys as $key) {
        $word = substr($key, strlen($dir) + 1);
        if (!str_starts_with($key, $dir . ':') || !isset($byKey[$word])) continue;
        if (in_array($key, array_column($tasks, 'key'), true)) continue;

        // Přepiš úlohu, která sama není z chybovníku
        foreach ($tasks as $i => $t) {
            if (in_array($t['key'], $keys, true)) continue;
            $tasks[$i] = englishBuildTask($byKey[$word], $pool, $qKey, $aKey, $dir);
            $done++;
            break;
        }
    }
    return $done;
}
