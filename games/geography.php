<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type'        => $_POST['game_type'] ?? 'geography',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => 'zemepisna mapa',
    ])]);
    exit;
}

// ─── ISO kódy → česká jména (Evropa) ─────────────────────────────────────
$europeISO = [
    '250'=>'Francie','724'=>'Španělsko','620'=>'Portugalsko','826'=>'Velká Británie',
    '372'=>'Irsko','578'=>'Norsko','752'=>'Švédsko','246'=>'Finsko','208'=>'Dánsko',
    '276'=>'Německo','616'=>'Polsko','203'=>'Česká republika','703'=>'Slovensko',
    '348'=>'Maďarsko','642'=>'Rumunsko','100'=>'Bulharsko','300'=>'Řecko',
    '688'=>'Srbsko','191'=>'Chorvatsko','40'=>'Rakousko','756'=>'Švýcarsko',
    '380'=>'Itálie','112'=>'Bělorusko','440'=>'Litva','428'=>'Lotyšsko',
    '233'=>'Estonsko','804'=>'Ukrajina','352'=>'Island','56'=>'Belgie',
    '528'=>'Nizozemsko','705'=>'Slovinsko','70'=>'Bosna a Hercegovina',
    '807'=>'Severní Makedonie','8'=>'Albánie','498'=>'Moldavsko',
];

// ─── ISO kódy → česká jména (Státy světa) ─────────────────────────────
// Bez duplicitních klíčů, ISO 3166-1 numeric
$worldISO = [
    '840'=>'USA','124'=>'Kanada','484'=>'Mexiko',
    '192'=>'Kuba','332'=>'Haiti','388'=>'Jamajka','214'=>'Dominikánská republika',
    '320'=>'Guatemala','340'=>'Honduras','222'=>'Salvador','558'=>'Nikaragua',
    '188'=>'Kostarika','591'=>'Panama',
    '076'=>'Brazílie','032'=>'Argentina','152'=>'Chile','170'=>'Kolumbie',
    '604'=>'Peru','862'=>'Venezuela','858'=>'Uruguay','068'=>'Bolívie',
    '218'=>'Ekvádor','600'=>'Paraguay',
    '012'=>'Alžírsko','818'=>'Egypt','504'=>'Maroko','788'=>'Tunisko','434'=>'Libye',
    '729'=>'Súdán','231'=>'Etiopie','404'=>'Keňa','800'=>'Uganda','834'=>'Tanzanie',
    '706'=>'Somálsko','288'=>'Ghana','566'=>'Nigérie','024'=>'Angola','508'=>'Mosambik',
    '710'=>'Jihoafrická republika','716'=>'Zimbabwe','516'=>'Namibie',
    '072'=>'Botswana','646'=>'Rwanda','108'=>'Burundi','562'=>'Niger','466'=>'Mali',
    '854'=>'Burkina Faso','384'=>'Pobřeží slonoviny','686'=>'Senegal',
    '120'=>'Kamerun','180'=>'DR Kongo','178'=>'Kongo','140'=>'Středoafrická republika',
    '148'=>'Čad','450'=>'Madagaskar',
    '156'=>'Čína','356'=>'Indie','392'=>'Japonsko','410'=>'Jižní Korea','408'=>'Severní Korea',
    '643'=>'Rusko','792'=>'Turecko','682'=>'Saúdská Arábie','364'=>'Írán','368'=>'Irák',
    '760'=>'Sýrie','400'=>'Jordánsko','422'=>'Libanon','376'=>'Izrael','512'=>'Omán',
    '784'=>'Spojené arabské emiráty','634'=>'Katar','414'=>'Kuvajt','050'=>'Bangladéš',
    '144'=>'Srí Lanka','524'=>'Nepál','586'=>'Pákistán',
    '004'=>'Afghánistán','860'=>'Uzbekistán','398'=>'Kazachstán','417'=>'Kyrgyzstán',
    '762'=>'Tádžikistán','795'=>'Turkmenistán','704'=>'Vietnam','764'=>'Thajsko',
    '116'=>'Kambodža','418'=>'Laos','104'=>'Myanmar','458'=>'Malajsie','360'=>'Indonésie',
    '608'=>'Filipíny','496'=>'Mongolsko',
    '036'=>'Austrálie','554'=>'Nový Zéland','598'=>'Papua Nová Guinea',
];

// ─── Česká města (GeoJSON Point features) ────────────────────────────────
$czechCities = ['type'=>'FeatureCollection','features'=>[
    ['type'=>'Feature','properties'=>['name'=>'Praha'],         'geometry'=>['type'=>'Point','coordinates'=>[14.420, 50.088]]],
    ['type'=>'Feature','properties'=>['name'=>'Brno'],          'geometry'=>['type'=>'Point','coordinates'=>[16.608, 49.195]]],
    ['type'=>'Feature','properties'=>['name'=>'Ostrava'],       'geometry'=>['type'=>'Point','coordinates'=>[18.292, 49.835]]],
    ['type'=>'Feature','properties'=>['name'=>'Plzeň'],         'geometry'=>['type'=>'Point','coordinates'=>[13.377, 49.738]]],
    ['type'=>'Feature','properties'=>['name'=>'Liberec'],       'geometry'=>['type'=>'Point','coordinates'=>[15.056, 50.768]]],
    ['type'=>'Feature','properties'=>['name'=>'Olomouc'],       'geometry'=>['type'=>'Point','coordinates'=>[17.252, 49.594]]],
    ['type'=>'Feature','properties'=>['name'=>'České Budějovice'],'geometry'=>['type'=>'Point','coordinates'=>[14.475, 48.975]]],
    ['type'=>'Feature','properties'=>['name'=>'Hradec Králové'],'geometry'=>['type'=>'Point','coordinates'=>[15.832, 50.210]]],
    ['type'=>'Feature','properties'=>['name'=>'Ústí nad Labem'],'geometry'=>['type'=>'Point','coordinates'=>[13.986, 50.660]]],
    ['type'=>'Feature','properties'=>['name'=>'Pardubice'],     'geometry'=>['type'=>'Point','coordinates'=>[15.779, 50.039]]],
    ['type'=>'Feature','properties'=>['name'=>'Zlín'],          'geometry'=>['type'=>'Point','coordinates'=>[17.667, 49.226]]],
    ['type'=>'Feature','properties'=>['name'=>'Havířov'],       'geometry'=>['type'=>'Point','coordinates'=>[18.362, 49.778]]],
    ['type'=>'Feature','properties'=>['name'=>'Kladno'],        'geometry'=>['type'=>'Point','coordinates'=>[14.101, 50.148]]],
    ['type'=>'Feature','properties'=>['name'=>'Most'],          'geometry'=>['type'=>'Point','coordinates'=>[13.637, 50.503]]],
    ['type'=>'Feature','properties'=>['name'=>'Opava'],         'geometry'=>['type'=>'Point','coordinates'=>[17.902, 49.938]]],
    ['type'=>'Feature','properties'=>['name'=>'Frýdek-Místek'], 'geometry'=>['type'=>'Point','coordinates'=>[18.367, 49.682]]],
    ['type'=>'Feature','properties'=>['name'=>'Karviná'],       'geometry'=>['type'=>'Point','coordinates'=>[18.541, 49.857]]],
    ['type'=>'Feature','properties'=>['name'=>'Jihlava'],       'geometry'=>['type'=>'Point','coordinates'=>[15.591, 49.396]]],
    ['type'=>'Feature','properties'=>['name'=>'Teplice'],       'geometry'=>['type'=>'Point','coordinates'=>[13.825, 50.638]]],
    ['type'=>'Feature','properties'=>['name'=>'Karlovy Vary'],  'geometry'=>['type'=>'Point','coordinates'=>[12.872, 50.232]]],
    ['type'=>'Feature','properties'=>['name'=>'Děčín'],         'geometry'=>['type'=>'Point','coordinates'=>[14.215, 50.774]]],
    ['type'=>'Feature','properties'=>['name'=>'Chomutov'],      'geometry'=>['type'=>'Point','coordinates'=>[13.418, 50.459]]],
    ['type'=>'Feature','properties'=>['name'=>'Přerov'],        'geometry'=>['type'=>'Point','coordinates'=>[17.452, 49.456]]],
    ['type'=>'Feature','properties'=>['name'=>'Mladá Boleslav'],'geometry'=>['type'=>'Point','coordinates'=>[14.907, 50.412]]],
]];

// ─── Evropské řeky — záložní inline data (použijí se pokud CDN selže) ──────
// Primárně načítáme z CDN Natural Earth (ne_50m_rivers)
// Klíče v RIVERS_NAMES musí odpovídat anglickým názvům v Natural Earth datech
$riversNames = [
    'Vltava'=>'Vltava','Elbe'=>'Labe','Danube'=>'Dunaj','Rhine'=>'Rýn',
    'Volga'=>'Volha','Loire'=>'Loira','Thames'=>'Temže','Oder'=>'Odra',
    'Vistula'=>'Wisła','Dnieper'=>'Dněpr','Morava'=>'Morava',
    'Seine'=>'Seina','Po'=>'Pád','Tagus'=>'Tajo','Ebro'=>'Ebro',
    'Elbe'=>'Labe',
];
// Záložní inline GeoJSON (zjednodušené, ale lepší než nic)
$europeanRivers = ['type'=>'FeatureCollection','features'=>[
    ['type'=>'Feature','properties'=>['name'=>'Vltava'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [13.65,48.57],[13.72,48.65],[13.80,48.73],[13.88,48.80],[13.95,48.90],
        [14.00,49.00],[14.05,49.10],[14.10,49.22],[14.15,49.35],[14.18,49.48],
        [14.20,49.57],[14.22,49.67],[14.24,49.75],[14.27,49.82],[14.30,49.90],
        [14.33,49.97],[14.37,50.04],[14.41,50.09]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Labe'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [15.56,50.77],[15.35,50.65],[15.10,50.52],[14.90,50.42],[14.70,50.33],
        [14.48,50.24],[14.22,50.16],[14.00,50.15],[13.80,50.25],[13.60,50.38],
        [13.45,50.55],[13.30,50.70],[13.10,51.00],[12.90,51.15],[12.60,51.30],
        [12.00,51.43],[11.55,51.55],[11.00,51.68],[10.50,51.78],[10.00,51.92],
        [9.40,52.10],[9.10,52.30],[9.20,52.80],[9.35,53.10],[9.50,53.35],
        [9.65,53.50],[9.82,53.63],[9.95,53.84]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Dunaj'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [8.15,48.02],[8.80,47.90],[9.50,47.75],[10.20,47.72],[10.80,47.73],
        [11.40,47.78],[12.00,47.80],[12.60,47.82],[13.05,47.98],[13.55,48.10],
        [13.95,48.28],[14.50,48.20],[15.00,48.12],[15.55,48.10],[16.00,48.10],
        [16.38,48.20],[16.90,47.95],[17.20,47.78],[17.60,47.76],[18.18,47.76],
        [18.70,47.76],[19.10,47.77],[19.55,46.88],[20.10,46.78],[20.75,46.28],
        [21.20,45.90],[21.75,45.57],[22.20,45.30],[22.70,44.98],[23.20,44.68],
        [24.00,44.35],[24.80,44.38],[25.50,44.22],[26.20,44.05],[27.00,44.08],
        [28.00,43.73],[28.70,43.73],[29.20,43.75],[29.70,43.75],[30.50,45.50]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Rýn'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [8.22,47.00],[7.98,47.25],[7.78,47.50],[7.60,47.75],[7.55,48.00],
        [7.60,48.35],[7.68,48.60],[7.75,48.90],[7.80,49.20],[7.75,49.55],
        [7.62,50.00],[6.95,50.45],[6.70,50.80],[6.40,51.00],[6.15,51.25],
        [5.90,51.50],[5.70,51.75],[5.35,51.92],[4.90,52.00],[4.40,51.98],
        [4.00,51.98]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Volha'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [32.45,57.28],[33.20,56.95],[34.30,56.60],[35.80,56.30],[37.50,55.85],
        [38.50,55.30],[40.00,55.00],[41.00,54.00],[42.00,53.45],[43.20,52.80],
        [44.00,52.00],[44.80,51.40],[46.00,50.10],[47.00,49.00],[48.00,48.00],
        [48.60,47.20],[49.10,46.70],[49.80,46.20],[50.30,45.70],[51.00,45.35],
        [51.60,45.00],[51.80,45.00],[51.88,44.90]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Odra'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [17.68,49.62],[17.82,50.00],[17.92,50.30],[17.85,50.60],[17.75,50.90],
        [17.60,51.20],[17.35,51.50],[17.00,51.80],[16.55,52.10],[16.00,52.50],
        [15.50,52.85],[14.75,53.10],[14.50,53.40],[14.30,53.70],[14.20,54.10]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Wisła'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [18.88,49.68],[18.98,49.90],[19.15,50.05],[19.60,50.08],[20.20,50.08],
        [20.80,50.20],[21.20,50.50],[21.50,50.90],[21.70,51.30],[21.60,51.70],
        [21.40,52.00],[21.10,52.30],[20.85,52.60],[20.60,53.00],[19.95,53.35],
        [19.30,53.75],[18.90,54.00],[18.60,54.20],[18.75,54.40]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Dněpr'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [33.50,54.50],[33.00,53.80],[32.20,53.20],[31.00,52.40],[30.50,51.50],
        [30.45,50.80],[30.52,50.45],[30.50,49.80],[31.20,49.10],[32.00,48.50],
        [33.40,47.75],[34.10,47.40],[34.65,47.10],[34.88,46.80],[32.20,46.50],
        [31.60,46.55],[31.00,46.62]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Loira'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [3.90,44.43],[3.70,44.60],[3.50,44.90],[3.25,45.40],[2.80,46.10],
        [2.40,46.80],[1.90,47.20],[1.60,47.42],[1.10,47.42],[0.65,47.42],
        [0.10,47.42],[-0.40,47.35],[-0.80,47.30],[-1.30,47.25],[-2.00,47.27]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Temže'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [-2.00,51.65],[-1.70,51.72],[-1.30,51.75],[-0.90,51.72],[-0.55,51.68],
        [-0.30,51.62],[-0.10,51.58],[0.20,51.56],[0.50,51.55]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Seina'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [5.00,47.80],[4.80,47.60],[4.40,47.50],[3.80,47.52],[3.20,47.60],
        [2.80,47.90],[2.40,48.35],[2.30,48.50],[2.25,48.70],[2.10,48.90],
        [1.70,49.10],[1.20,49.25],[0.80,49.40],[0.35,49.50],[0.00,49.50],
        [-0.20,49.43],[-0.40,49.42]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Pád'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [7.10,44.78],[7.35,44.90],[7.60,44.95],[7.95,44.95],[8.30,45.02],
        [8.65,45.08],[9.00,45.10],[9.35,45.08],[9.70,45.08],[10.00,45.08],
        [10.40,45.02],[10.80,44.95],[11.20,44.87],[11.55,44.82],[11.90,44.87],
        [12.20,44.90],[12.45,44.90]
    ]]],
    ['type'=>'Feature','properties'=>['name'=>'Morava'],'geometry'=>['type'=>'LineString','coordinates'=>[
        [16.85,50.10],[16.90,49.95],[16.95,49.80],[17.05,49.65],[17.12,49.48],
        [17.05,49.30],[16.95,49.10],[16.88,48.95],[16.82,48.82],[16.88,48.70],
        [17.00,48.62],[17.12,48.55]
    ]]],
]];

// ─── Moře a oceány ────────────────────────────────────────────────────────
// Malá moře: Polygon | Velké oceány: Point (CircleMarker)
// Leaflet neumí kreslit polygony přes velké části mapy bez projekčního pluginu
$seas = ['type'=>'FeatureCollection','features'=>[

// ── MALÁ MOŘE — polygony ─────────────────────────────────────────────────
['type'=>'Feature','properties'=>['name'=>'Středozemní moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-5.5,36.0],[-2.0,35.2],[2.5,37.2],[5.5,43.5],[8.5,44.2],[12.5,44.8],
    [15.5,45.5],[18.5,40.5],[20.5,38.8],[23.0,37.8],[26.0,36.8],[29.5,36.5],
    [32.5,37.0],[35.5,36.5],[36.8,36.5],[36.8,33.0],[28.0,30.5],[20.0,30.2],
    [12.0,30.2],[5.0,31.0],[0.0,32.5],[-5.5,35.0],[-5.5,36.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Černé moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [27.8,41.0],[29.0,43.5],[30.5,46.5],[32.0,46.9],[34.0,46.5],[36.5,47.0],
    [38.5,47.2],[40.5,42.0],[41.5,41.5],[41.5,40.5],[40.0,40.0],[37.5,40.5],
    [34.5,40.5],[31.5,41.0],[29.5,41.0],[27.8,41.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Kaspické moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [49.5,37.5],[50.5,38.5],[52.0,40.0],[53.5,41.0],[54.0,42.5],[54.0,44.5],
    [53.5,46.0],[53.0,47.2],[51.5,47.5],[50.5,46.0],[50.0,44.5],[49.5,43.0],
    [49.0,41.5],[49.5,40.0],[50.0,38.5],[49.5,37.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Baltské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [10.0,55.5],[10.5,57.5],[11.5,59.0],[14.0,60.0],[16.5,60.5],[18.5,60.0],
    [20.5,59.5],[22.5,60.0],[24.5,60.5],[26.0,59.5],[26.0,57.5],[24.0,56.0],
    [22.0,55.0],[19.0,55.0],[15.5,55.0],[13.0,55.0],[10.0,55.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Severní moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-2.0,51.5],[2.5,51.5],[5.5,53.5],[8.0,55.0],[8.5,57.5],[5.5,58.5],
    [2.0,59.5],[0.0,60.5],[-1.5,60.0],[-3.5,58.5],[-4.0,57.0],[-2.0,55.0],
    [-1.0,53.5],[-2.0,51.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Norské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-10.0,62.0],[-2.0,62.0],[4.0,61.0],[5.5,62.5],[6.0,65.5],[10.5,67.0],
    [14.0,68.0],[16.0,70.0],[18.0,71.5],[14.0,72.5],[8.0,71.5],[2.0,70.0],
    [-4.0,67.5],[-8.0,65.0],[-10.0,62.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Barentsovo moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [15.0,70.5],[22.0,70.5],[28.0,72.0],[33.0,73.5],[40.0,73.5],[48.0,73.0],
    [55.0,72.5],[58.0,71.5],[58.0,76.0],[48.0,78.0],[35.0,79.5],[22.0,80.0],
    [15.0,78.0],[15.0,70.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Rudé moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [32.5,29.5],[33.5,28.5],[35.0,27.0],[37.0,24.5],[39.0,22.5],[41.0,19.5],
    [43.0,16.5],[43.5,14.5],[43.0,15.0],[41.0,18.0],[39.5,20.5],[38.0,22.5],
    [36.5,25.5],[35.0,28.0],[33.5,29.5],[32.5,29.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Perský záliv'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [48.5,29.5],[50.0,29.0],[52.0,27.5],[54.0,25.5],[56.5,24.5],[57.5,23.5],
    [56.5,23.0],[55.0,23.5],[52.5,24.5],[50.5,26.5],[49.0,27.5],[48.5,29.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Arabské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [43.5,12.5],[46.0,11.5],[50.0,11.5],[55.0,12.0],[58.0,13.5],[60.0,16.0],
    [62.0,20.5],[63.0,23.5],[63.0,25.0],[58.0,24.5],[55.0,22.5],[51.5,20.0],
    [47.5,16.0],[44.5,14.0],[43.5,12.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Bengálský záliv'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [80.0,8.5],[82.0,7.5],[86.5,7.5],[90.0,8.0],[93.5,10.0],[97.5,13.5],
    [98.0,18.5],[96.0,22.5],[92.5,22.5],[89.0,21.5],[85.5,20.0],[82.5,16.5],
    [80.0,13.0],[80.0,8.5]
]]]],
['type'=>'Feature','properties'=>['name'=>'Jihočínské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [99.5,4.0],[102.0,2.0],[105.0,1.5],[109.0,2.0],[113.0,3.5],[118.0,5.5],
    [121.0,8.0],[121.5,13.5],[120.5,18.5],[118.0,22.5],[116.0,24.0],[113.0,22.5],
    [110.0,21.0],[107.0,18.0],[104.0,12.5],[101.0,7.5],[99.5,4.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Japonské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [128.5,34.0],[130.5,33.5],[132.0,33.5],[134.0,34.5],[135.5,36.5],
    [135.5,40.5],[134.0,43.0],[132.0,46.5],[130.0,48.0],[128.0,48.5],
    [127.0,47.5],[125.5,46.0],[124.0,44.0],[123.0,42.0],[124.0,40.0],
    [126.0,38.0],[127.0,36.5],[128.5,34.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Karibské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-87.0,16.0],[-85.0,12.0],[-83.0,10.0],[-78.0,9.5],[-73.0,10.5],
    [-68.0,10.5],[-63.0,10.5],[-60.0,11.5],[-60.0,15.5],[-63.0,18.0],
    [-67.5,20.0],[-72.0,20.5],[-77.5,20.5],[-80.0,23.5],[-83.5,24.0],
    [-87.5,21.5],[-90.0,18.5],[-89.0,17.0],[-87.0,16.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Mexický záliv'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-97.5,26.0],[-97.5,24.0],[-97.0,22.0],[-95.0,19.5],[-92.0,18.5],
    [-90.0,18.5],[-87.5,21.5],[-84.0,22.5],[-81.5,24.5],[-81.0,26.0],
    [-83.0,27.5],[-85.0,30.5],[-88.5,30.5],[-93.0,30.0],[-97.0,28.0],[-97.5,26.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Jaderské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [12.5,45.0],[13.2,43.8],[14.5,43.0],[15.5,42.0],[17.0,41.0],[18.5,40.5],
    [19.5,40.0],[20.0,40.5],[19.5,41.5],[18.5,42.5],[17.0,43.5],[15.5,44.5],
    [14.0,45.2],[12.5,45.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Egejské moře'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [22.0,37.0],[23.5,36.5],[25.0,36.0],[27.5,36.5],[28.0,37.5],[28.0,39.5],
    [26.5,40.0],[25.5,41.5],[24.0,41.5],[22.5,41.5],[21.5,40.5],[22.0,39.5],
    [22.5,38.5],[22.0,37.0]
]]]],
['type'=>'Feature','properties'=>['name'=>'Hudsonův záliv'],'geometry'=>['type'=>'Polygon','coordinates'=>[[
    [-95.0,63.5],[-90.0,64.5],[-83.5,65.5],[-78.0,63.5],[-77.0,60.5],
    [-78.0,57.5],[-80.5,57.0],[-83.0,56.0],[-86.0,55.5],[-90.0,56.5],
    [-94.0,58.0],[-96.0,61.0],[-95.0,63.5]
]]]],

// ── VELKÉ OCEÁNY — Point/CircleMarker (polygon by způsoboval artefakty) ──
['type'=>'Feature','properties'=>['name'=>'Tichý oceán'],        'geometry'=>['type'=>'Point','coordinates'=>[-150.0,  5.0]]],
['type'=>'Feature','properties'=>['name'=>'Atlantský oceán'],    'geometry'=>['type'=>'Point','coordinates'=>[ -30.0, 15.0]]],
['type'=>'Feature','properties'=>['name'=>'Indický oceán'],      'geometry'=>['type'=>'Point','coordinates'=>[  75.0,-20.0]]],
['type'=>'Feature','properties'=>['name'=>'Severní ledový oceán'],'geometry'=>['type'=>'Point','coordinates'=>[  0.0,  82.0]]],
['type'=>'Feature','properties'=>['name'=>'Jižní oceán'],        'geometry'=>['type'=>'Point','coordinates'=>[  0.0, -60.0]]],
]];

// ─── Definice dostupných map ──────────────────────────────────────────────
$mapDefs = [
    'europe'    => ['label'=>'🌍 Evropa — státy',    'hint'=>'Jak se jmenuje zvýrazněný stát?'],
    'czech'     => ['label'=>'🇨🇿 Kraje ČR',         'hint'=>'Jak se jmenuje zvýrazněný kraj?'],
    'world'     => ['label'=>'🌐 Státy světa',        'hint'=>'Jak se jmenuje zvýrazněný stát?'],
    'cities_cz' => ['label'=>'🏙 Česká města',        'hint'=>'Které město je označeno?'],
    'rivers_eu' => ['label'=>'🌊 Evropské řeky',      'hint'=>'Jak se jmenuje zvýrazněná řeka?'],
    'seas'      => ['label'=>'🌊 Moře a oceány',      'hint'=>'Jak se jmenuje zvýrazněné moře nebo oceán?'],
];

// ─── Otázky (textové) ─────────────────────────────────────────────────────
$categories = [
    'Hlavní města Evropy' => [
        ['q'=>'Hlavní město Francie:',       'a'=>'Paříž',      'w'=>['Brusel','Lyon']],
        ['q'=>'Hlavní město Německa:',       'a'=>'Berlín',     'w'=>['Hamburg','Frankfurt']],
        ['q'=>'Hlavní město Itálie:',        'a'=>'Řím',        'w'=>['Milán','Neapol']],
        ['q'=>'Hlavní město Španělska:',     'a'=>'Madrid',     'w'=>['Barcelona','Sevilla']],
        ['q'=>'Hlavní město Polska:',        'a'=>'Varšava',    'w'=>['Krakov','Gdaňsk']],
        ['q'=>'Hlavní město Rakouska:',      'a'=>'Vídeň',      'w'=>['Salzburg','Linec']],
        ['q'=>'Hlavní město Maďarska:',      'a'=>'Budapešť',   'w'=>['Debrecín','Miskolc']],
        ['q'=>'Hlavní město Slovenska:',     'a'=>'Bratislava', 'w'=>['Košice','Prešov']],
        ['q'=>'Hlavní město Chorvatska:',    'a'=>'Záhřeb',     'w'=>['Split','Rijeka']],
        ['q'=>'Hlavní město Řecka:',         'a'=>'Atény',      'w'=>['Soluň','Patras']],
        ['q'=>'Hlavní město Švédska:',       'a'=>'Stockholm',  'w'=>['Göteborg','Malmö']],
        ['q'=>'Hlavní město Norska:',        'a'=>'Oslo',       'w'=>['Bergen','Trondheim']],
        ['q'=>'Hlavní město Dánska:',        'a'=>'Kodaň',      'w'=>['Aarhus','Odense']],
        ['q'=>'Hlavní město Finska:',        'a'=>'Helsinky',   'w'=>['Tampere','Turku']],
        ['q'=>'Hlavní město Belgie:',        'a'=>'Brusel',     'w'=>['Antverpy','Lutych']],
        ['q'=>'Hlavní město Nizozemska:',    'a'=>'Amsterdam',  'w'=>['Rotterdam','Haag']],
        ['q'=>'Hlavní město Rumunska:',      'a'=>'Bukurešť',   'w'=>['Kluž','Temešvár']],
        ['q'=>'Hlavní město Bulharska:',     'a'=>'Sofie',      'w'=>['Plovdiv','Varna']],
        ['q'=>'Hlavní město Srbska:',        'a'=>'Bělehrad',   'w'=>['Novi Sad','Niš']],
        ['q'=>'Hlavní město Ukrainy:',       'a'=>'Kyjev',      'w'=>['Charkov','Oděsa']],
    ],
    'Hlavní města světa' => [
        ['q'=>'Hlavní město USA:',           'a'=>'Washington', 'w'=>['New York','Los Angeles']],
        ['q'=>'Hlavní město Kanady:',        'a'=>'Ottawa',     'w'=>['Toronto','Vancouver']],
        ['q'=>'Hlavní město Brazílie:',      'a'=>'Brasília',   'w'=>['Rio de Janeiro','São Paulo']],
        ['q'=>'Hlavní město Argentiny:',     'a'=>'Buenos Aires','w'=>['Córdoba','Rosario']],
        ['q'=>'Hlavní město Austrálie:',     'a'=>'Canberra',   'w'=>['Sydney','Melbourne']],
        ['q'=>'Hlavní město Japonska:',      'a'=>'Tokio',      'w'=>['Osaka','Kjóto']],
        ['q'=>'Hlavní město Číny:',          'a'=>'Peking',     'w'=>['Šanghaj','Hongkong']],
        ['q'=>'Hlavní město Indie:',         'a'=>'Naí Dillí',  'w'=>['Bombaj','Kalkata']],
        ['q'=>'Hlavní město Ruska:',         'a'=>'Moskva',     'w'=>['Petrohrad','Novosibirsk']],
        ['q'=>'Hlavní město Egypta:',        'a'=>'Káhira',     'w'=>['Alexandrie','Gíza']],
        ['q'=>'Hlavní město JAR:',           'a'=>'Pretoria',   'w'=>['Kapské Město','Johannesburg']],
        ['q'=>'Hlavní město Mexika:',        'a'=>'Mexiko City','w'=>['Guadalajara','Monterrey']],
        ['q'=>'Hlavní město Turecka:',       'a'=>'Ankara',     'w'=>['Istanbul','Izmir']],
        ['q'=>'Hlavní město Saúdské Arábie:','a'=>'Rijád',      'w'=>['Jidda','Mekka']],
        ['q'=>'Hlavní město Pákistánu:',     'a'=>'Islámábád',  'w'=>['Karáčí','Láhaur']],
    ],
    'Česká republika' => [
        ['q'=>'Hlavní město ČR:',                   'a'=>'Praha',        'w'=>['Brno','Ostrava']],
        ['q'=>'Druhé největší město ČR:',           'a'=>'Brno',         'w'=>['Ostrava','Plzeň']],
        ['q'=>'Nejvyšší hora ČR:',                  'a'=>'Sněžka',       'w'=>['Praděd','Lysá hora']],
        ['q'=>'Nejdelší řeka ČR:',                  'a'=>'Vltava',       'w'=>['Labe','Morava']],
        ['q'=>'Největší jezero ČR:',                'a'=>'Černé jezero', 'w'=>['Čertovo jezero','Plešné jezero']],
        ['q'=>'Kraj s hlavním městem Ostrava:',     'a'=>'Moravskoslezský kraj','w'=>['Olomoucký kraj','Zlínský kraj']],
        ['q'=>'Kraj s hlavním městem Brno:',        'a'=>'Jihomoravský kraj','w'=>['Kraj Vysočina','Zlínský kraj']],
        ['q'=>'Sousední stát na severu:',           'a'=>'Polsko',       'w'=>['Německo','Slovensko']],
        ['q'=>'Sousední stát na jihu:',             'a'=>'Rakousko',     'w'=>['Slovensko','Maďarsko']],
        ['q'=>'Počet krajů v ČR:',                  'a'=>'14',           'w'=>['12','16']],
        ['q'=>'Přehrada Lipno leží na řece:',       'a'=>'Vltava',       'w'=>['Labe','Dyje']],
        ['q'=>'Nejstarší česká universita:',        'a'=>'Karlova universita','w'=>['Masarykova universita','ČVUT']],
    ],
    'Řeky a hory světa' => [
        ['q'=>'Nejdelší řeka světa:',          'a'=>'Nil',         'w'=>['Amazonka','Mississippi']],
        ['q'=>'Druhá nejdelší řeka světa:',    'a'=>'Amazonka',    'w'=>['Nil','Jang-c\'tiang']],
        ['q'=>'Nejvyšší hora světa:',          'a'=>'Everest',     'w'=>['K2','Kančendžonga']],
        ['q'=>'Nejvyšší hora Evropy:',         'a'=>'Elbrus',      'w'=>['Mont Blanc','Dufour']],
        ['q'=>'Nejvyšší hora Afriky:',         'a'=>'Kilimandžáro','w'=>['Keňa','Rwenzori']],
        ['q'=>'Nejvyšší hora Ameriky:',        'a'=>'Aconcagua',   'w'=>['Denali','Chimborazo']],
        ['q'=>'Nejhlubší jezero světa:',       'a'=>'Bajkal',      'w'=>['Kaspické moře','Tanganika']],
        ['q'=>'Největší oceán:',               'a'=>'Tichý oceán', 'w'=>['Atlantský oceán','Indický oceán']],
        ['q'=>'Největší poušť světa:',         'a'=>'Sahara',      'w'=>['Gobi','Arabská poušť']],
        ['q'=>'Nejdelší řeka Evropy:',         'a'=>'Volha',       'w'=>['Dunaj','Rýn']],
        ['q'=>'Pohoří Evropa–Asie:',           'a'=>'Ural',        'w'=>['Kavkaz','Himaláje']],
        ['q'=>'Největší ostrov světa:',        'a'=>'Grónsko',     'w'=>['Nová Guinea','Borneo']],
        ['q'=>'Největší kontinent:',           'a'=>'Asie',        'w'=>['Afrika','Evropa']],
        ['q'=>'Nejmenší kontinent:',           'a'=>'Austrálie',   'w'=>['Evropa','Antarktida']],
    ],
];

$cat     = $_GET['cat']  ?? array_key_first($categories);
$mapType = $_GET['map']  ?? 'europe';
$mode    = $_GET['mode'] ?? 'questions';
if (!isset($categories[$cat])) $cat = array_key_first($categories);
if (!isset($mapDefs[$mapType])) $mapType = 'europe';

// Připrav otázky s choices
$rawQ = $categories[$cat];
shuffle($rawQ);
$rawQ = array_slice($rawQ, 0, 12);
$questions = array_map(fn($q) => [
    'q'       => $q['q'],
    'a'       => $q['a'],
    'choices' => (function() use ($q) {
        $arr = array_merge($q['w'], [$q['a']]);
        shuffle($arr);
        return $arr;
    })(),
], $rawQ);

$pageTitle = 'Zeměpis';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($mode === 'map'): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
#mapLeaflet { height: min(460px, 60vh); border-radius: 10px; background: #0d1b2e; }
.leaflet-tile-pane { filter: grayscale(40%) brightness(0.7) hue-rotate(200deg); }
</style>
<?php endif; ?>

<div class="page-header">
    <h1>🌍 <span class="accent">Zeměpis</span></h1>
</div>

<div class="mode-tabs">
    <a href="?mode=questions&cat=<?= urlencode($cat) ?>" class="mode-tab <?= $mode==='questions'?'active':'' ?>">❓ Otázky</a>
    <a href="?mode=map&map=<?= $mapType ?>"              class="mode-tab <?= $mode==='map'      ?'active':'' ?>">🗺 Slepé mapy</a>
</div>

<?php if ($mode === 'questions'): ?>
<!-- ══ OTÁZKY ═══════════════════════════════════════════════════════════════ -->
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Kategorie:</span>
        <?php foreach (array_keys($categories) as $c): ?>
        <a href="?mode=questions&cat=<?= urlencode($c) ?>" class="filter-btn <?= $c===$cat?'active':'' ?>"><?= htmlspecialchars($c) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="game-container mc-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain"><?= count($questions) ?></span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
    </div>
    <div id="startWrapper" style="text-align:center;padding:2.5rem 0">
        <p style="color:var(--muted);margin-bottom:1.25rem">Vyber správnou odpověď ze 3 možností.<br>Klávesy <kbd>A</kbd> <kbd>B</kbd> <kbd>C</kbd> nebo kliknutí.</p>
        <button id="startBtn" class="btn-primary" style="font-size:1.1rem;padding:.85rem 2.5rem">Začít ▶</button>
    </div>
    <div class="mc-question-wrapper" id="mcWrapper" style="display:none">
        <div class="mc-progress-dots" id="mcDots"></div>
        <div class="mc-question" id="mcQuestion"></div>
        <div class="mc-choices" id="mcChoices"></div>
        <div class="mc-feedback" id="mcFeedback"></div>
    </div>
    <div class="progress-bar-wrapper" style="margin-top:1.25rem">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🌍 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="geoMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?mode=questions&cat=<?= urlencode($cat) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>
<script>
const GEO_QUESTIONS = <?= json_encode(array_values($questions)) ?>;
const SAVE_URL = '<?= BASE_URL ?>/games/geography.php';
</script>
<script src="<?= BASE_URL ?>/js/geo_mc.js"></script>

<?php else: ?>
<!-- ══ SLEPÉ MAPY ════════════════════════════════════════════════════════════ -->
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group" style="flex-wrap:wrap">
        <span class="filter-label">Mapa:</span>
        <?php foreach ($mapDefs as $key => $def): ?>
        <a href="?mode=map&map=<?= $key ?>" class="filter-btn <?= $mapType===$key?'active':'' ?>"><?= $def['label'] ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="map-game-wrapper">
    <div class="map-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="mapScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapRemain">–</span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapTime">0</span><span class="gstat-label">sekund</span></div>
    </div>

    <div class="map-question-bar" style="margin-bottom:.75rem">
        <div class="map-hint" id="mapHint"><?= htmlspecialchars($mapDefs[$mapType]['hint']) ?></div>
        <div id="mapFeedback" class="math-feedback" style="min-height:1.5rem;font-size:1rem"></div>
    </div>

    <div id="mapLeaflet"></div>

    <div id="mapChoicesWrapper" style="max-width:580px;margin:1rem auto 0">
        <div class="mc-choices" id="mapChoices" style="display:none"></div>
        <div style="text-align:center;margin-top:.75rem">
            <button id="mapStartBtn" class="btn-primary">Začít ▶</button>
            <button id="mapResetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
        </div>
        <div style="text-align:center;margin-top:.4rem;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">
            Klávesy <kbd>A</kbd> <kbd>B</kbd> <kbd>C</kbd> nebo kliknutí
        </div>
    </div>

    <div class="progress-bar-wrapper" style="margin-top:1rem">
        <div class="progress-bar" id="mapProgress" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="mapResultsPanel" style="display:none">
    <h2>🗺 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="mapResFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="mapMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?mode=map&map=<?= $mapType ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="mapSaveStatus" class="save-status"></div>
</div>

<script>
const MAP_TYPE     = '<?= $mapType ?>';
const RIVERS_NAMES = <?= json_encode($riversNames ?? []) ?>;
const MAP_HINT   = '<?= addslashes($mapDefs[$mapType]['hint']) ?>';
const EUROPE_ISO = <?= json_encode($europeISO) ?>;
const WORLD_ISO  = <?= json_encode($worldISO) ?>;
const MAP_DATA   = <?= json_encode(
    $mapType === 'cities_cz' ? $czechCities :
    ($mapType === 'rivers_eu' ? $europeanRivers : $seas)
, JSON_UNESCAPED_UNICODE) ?>;
const SAVE_URL   = '<?= BASE_URL ?>/games/geography.php';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/topojson/3.0.2/topojson.min.js"></script>
<script src="<?= BASE_URL ?>/js/blind_map.js"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
