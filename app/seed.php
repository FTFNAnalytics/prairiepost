<?php
/**
 * The Prairie Dispatch — first-run seed and site provisioning.
 * pp_seed() populates a fresh database: the founding site, desks, settings,
 * verified wire sources, and sample stories that demonstrate the design.
 * pp_create_site() provisions an additional site joining a shared database.
 *
 * NOTE: everything here uses the passed-in $pdo, never db() — these run while
 * the connection is still being established.
 */

function pp_seed_last_id(PDO $pdo, string $table): int
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? (int) $pdo->lastInsertId($table . '_id_seq')
        : (int) $pdo->lastInsertId();
}

/** Default per-site settings for a new site row. */
function pp_site_default_settings(string $title): array
{
    return [
        'site_title'         => $title,
        'tagline'            => 'News to the horizon',
        'meta_description'   => 'A regional daily for people who live between the towns: farm and market news alongside council, courts, weather and community.',
        'footer_line'        => 'A regional daily for people who live between the towns: farm and market news alongside council, courts, weather and community.',
        'newsletter_heading' => 'The 6 a.m.',
        'newsletter_copy'    => 'One email before the day starts: council, markets, weather, and what happened overnight.',
        'weather_line'       => '',
        'regions'            => json_encode([
            'local'       => 'Local & county',
            'alberta'     => 'Alberta',
            'canada'      => 'Canada',
            'agriculture' => 'Agriculture wire',
        ]),
        'markets'            => '',
        'markets_note'       => '',
        'weather_today'      => '',
        'ad_top'             => '',
        'ad_rail'            => '',
        'ad_article'         => '',
        'analytics_code'     => '',
        'cron_secret'        => bin2hex(random_bytes(16)),
        /* --- Mail & the 6 a.m. ------------------------------------------ */
        'smtp_host'          => '',      // empty = PHP mail() fallback
        'smtp_port'          => '587',
        'smtp_user'          => '',
        'smtp_pass'          => '',
        'smtp_secure'        => 'tls',   // tls | ssl | none
        'mail_from'          => '',      // e.g. sixam@prairiedispatch.com
        'mail_from_name'     => $title,
        'paper_address'      => '',      // CASL: the paper's mailing address
        'newsletter_enabled' => '0',
        'newsletter_send_hour' => '6',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'contact_email'      => '',
        'traffic_items'      => '',
        'events_items'       => '',
        'field_notes_text'   => '',
        'field_notes_url'    => '',
    ];
}

/** Create a site row plus its default settings; returns the site row. */
function pp_create_site(PDO $pdo, string $slug, ?string $name = null): array
{
    $name = $name ?: ucwords(str_replace('-', ' ', $slug));
    $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')
        ->execute([$name, $slug, date('Y-m-d H:i:s')]);
    $siteId = pp_seed_last_id($pdo, 'sites');

    $ins = $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)');
    foreach (pp_site_default_settings($name) as $k => $v) {
        $ins->execute([$siteId, $k, $v]);
    }

    $stmt = $pdo->prepare('SELECT * FROM sites WHERE id = ?');
    $stmt->execute([$siteId]);
    return $stmt->fetch();
}

function pp_seed(PDO $pdo): void
{
    /* --- The founding site ----------------------------------------------- */
    $slug = slugify((string) pp_config('site_slug', 'prairiedispatch'));
    $siteName = $slug === 'prairiedispatch'
        ? 'The Prairie Dispatch'
        : 'The ' . ucwords(str_replace('-', ' ', $slug));
    $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')
        ->execute([$siteName, $slug, date('Y-m-d H:i:s')]);
    $siteId = pp_seed_last_id($pdo, 'sites');

    /* --- Desks: each one owns a colour ---------------------------------- */
    $desks = [
        // name, slug, colour, colour is fill-only, description, sort
        ['News',               'news',        '#17301C', 0, 'Council, courts, and what happened overnight across the region.', 1],
        ['Politics',           'politics',    '#2F6C99', 0, 'The province, the counties, and the money that moves between them.', 2],
        ['Agriculture',        'agriculture', '#3F5A22', 0, 'Crops, cattle, contracts and the weather that decides all three.', 3],
        ['Business & Markets', 'business',    '#7A661F', 0, 'Closing prices, main-street turnover, and who is building what.', 4],
        ['Community',          'community',   '#6F5535', 0, 'Rinks, halls, fairs, and the people who keep them open.', 5],
        ['Opinion',            'opinion',     '#9C3B22', 0, 'Signed columns and the editorial board. Rationed, like the red.', 6],
        ['Weather',            'weather',     '#77B2D6', 1, 'The forecast to the horizon, and what it means for the field.', 7],
    ];
    $insCat = $pdo->prepare('INSERT INTO categories (name, slug, color, color_is_fill, description, sort) VALUES (?, ?, ?, ?, ?, ?)');
    $catId = [];
    foreach ($desks as $d) {
        $insCat->execute($d);
        $catId[$d[1]] = pp_seed_last_id($pdo, 'categories');
    }

    /* --- Settings -------------------------------------------------------- */
    $settings = array_merge(pp_site_default_settings($siteName), [
        'weather_line'  => '21° and clearing · Wind NW 30 km/h',
        'markets'       => json_encode([
            ['Canola Nov',  '687.40', '+4.10'],
            ['Wheat CWRS',  '318.75', '-1.25'],
            ['Barley Feed', '241.00', '+0.75'],
            ['Steers 850lb','402.50', '+2.00'],
        ]),
        'markets_note'  => 'Prices last updated 4:15 p.m.',
        'weather_today' => json_encode([
            'temp' => '21°', 'hi' => '24°', 'lo' => '9°',
            'line' => 'Sun through the afternoon, wind out of the northwest at 30 km/h. Frost risk in the valley bottoms overnight.',
        ]),
    ]);
    $insSet = $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)');
    foreach ($settings as $k => $v) {
        $insSet->execute([$siteId, $k, $v]);
    }

    /* --- Wire sources (verified working at build time) ------------------- */
    $sources = [
        ['Drumheller Mail',      'https://www.drumhellermail.com/news?format=feed&type=rss', 'local'],
        ['ECA Review',           'https://ecareview.com/feed/',                              'local'],
        ['Strathmore Times',     'https://strathmoretimes.com/feed/',                        'local'],
        ['CBC Calgary',          'https://www.cbc.ca/webfeed/rss/rss-canada-calgary',        'local'],
        ['CBC Edmonton',         'https://www.cbc.ca/webfeed/rss/rss-canada-edmonton',       'alberta'],
        ['Global Calgary',       'https://globalnews.ca/calgary/feed/',                      'alberta'],
        ['Global Edmonton',      'https://globalnews.ca/edmonton/feed/',                     'alberta'],
        ['CBC Top Stories',      'https://www.cbc.ca/webfeed/rss/rss-topstories',            'canada'],
        ['CBC Canada',           'https://www.cbc.ca/webfeed/rss/rss-canada',                'canada'],
        ['Global National',      'https://globalnews.ca/canada/feed/',                       'canada'],
        ['Canadian Cattlemen',   'https://www.canadiancattlemen.ca/feed/',                   'agriculture'],
        ['Grainews',             'https://www.grainews.ca/feed/',                            'agriculture'],
        ['Manitoba Co-operator', 'https://www.manitobacooperator.ca/feed/',                  'agriculture'],
        ['Farmtario',            'https://farmtario.com/feed/',                              'agriculture'],
    ];
    $insSrc = $pdo->prepare('INSERT INTO sources (name, url, region, enabled) VALUES (?, ?, ?, 1)');
    foreach ($sources as $s) {
        $insSrc->execute($s);
    }

    /* --- Sample stories --------------------------------------------------- */
    $ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
    $p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';

    $stories = [
        [
            'title' => 'Canola contracts move early as growers watch a dry June',
            'desk' => 'agriculture', 'dateline' => 'Three Hills', 'byline' => 'Dana Ruthven',
            'lede' => 'Elevators north of the river are signing at levels last seen in 2023, and nobody wants to be the last one to price.',
            'image' => '/assets/img/photo-01.svg', 'image_caption' => 'Stubble east of Three Hills, where most of last year\'s crop went in the bin dry.', 'image_credit' => 'Staff photo',
            'featured' => 1, 'published' => $ago('-3 hours'),
            'tags' => 'canola, grain markets, drought',
            'body' => $p(
                'The first new-crop canola contracts of the season went out this week at $687 a tonne for November delivery, and by Thursday afternoon two elevators between Three Hills and Trochu said their sign-up sheets were the fullest they had been since the drought year.',
                'The number itself is not the story. The date is. Contracts usually firm up in late July, once growers have seen the crop through flowering. Signing in the first week of June means operators are pricing a crop that is barely out of the ground — and betting the dry spring holds.',
                '"We had eleven millimetres in May," said one agronomist working fields along Highway 21, who put the district about two weeks ahead of normal stress. "If we catch rain in the next ten days this looks clever. If we don\'t, it looks like the only smart move anybody made."',
                'The elevators are betting the same direction. Basis levels north of the river narrowed twelve dollars in a week, which is the grain trade\'s way of saying it would rather own bushels now than argue about them in October.'
            ),
        ],
        [
            'title' => 'Ninety minutes on one culvert: how Hanna council decides what a road is worth',
            'desk' => 'news', 'dateline' => 'Hanna', 'byline' => 'Wes Hartley',
            'lede' => 'The crossing on Range Road 131 failed in April. Fixing it costs $86,000, and four families use it every day.',
            'image' => '/assets/img/photo-02.svg', 'image_caption' => 'The approach to the failed crossing on Range Road 131.', 'image_credit' => 'Wes Hartley',
            'featured' => 0, 'published' => $ago('-6 hours'),
            'tags' => 'hanna, county council, roads',
            'body' => $p(
                'HANNA — Council spent ninety minutes on one culvert Tuesday night, and by the end of it had said most of what there is to say about rural budgets.',
                'The crossing on Range Road 131 washed out in the April melt. Public works priced a proper replacement at $86,000 — engineered, armoured, sized for a one-in-fifty flow. Four households and one cattle operation use the road daily; the detour adds nineteen kilometres each way.',
                'The debate was not about whether to fix it. It was about what a road owes the people on it. Councillor Reimer argued the county fixes crossings, full stop, or admits the map is a suggestion. Councillor Voss wanted the cheaper culvert at $31,000 and a load limit, and said so plainly: "We are not building for the flood we might get. We are building for the traffic we do get."',
                'The vote went 5–2 for the engineered crossing, with construction after harvest. The reeve summed up the ninety minutes in one line: "Nobody moves out here expecting pavement. They do expect to get home."'
            ),
        ],
        [
            'title' => 'Province rewrites the rural policing grant, and the counties do the math',
            'desk' => 'politics', 'dateline' => 'Stettler', 'byline' => 'Priya Chana',
            'lede' => 'The new formula weighs road kilometres instead of population. For counties with more grid than people, that changes everything.',
            'image' => '/assets/img/photo-03.svg', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-9 hours'),
            'tags' => 'policing, provincial budget, counties',
            'body' => $p(
                'STETTLER — The province quietly posted the new rural policing cost-share formula on Friday, and county administrators spent the weekend running their own numbers before anyone at the legislature could run them first.',
                'The old grant divided costs by population, which rural municipalities have argued for a decade punishes exactly the places policing is hardest: more territory, fewer taxpayers. The new formula weights patrol area and road kilometres at forty per cent.',
                'For Stettler County the early arithmetic lands about $210,000 a year better off. For the Special Areas, which have more grid road than some provinces, the swing is larger — though the board is not celebrating until it sees the transition schedule, which phases in over four years.',
                'Two administrators, who asked not to be named because the file is still before their councils, said the same thing in different words: the formula is fairer, and fairness that arrives in year four is a promise, not a payment.'
            ),
        ],
        [
            'title' => 'The last grocery store between Trochu and Delia changes hands',
            'desk' => 'business', 'dateline' => 'Trochu', 'byline' => 'June Kowalski',
            'lede' => 'After twenty-nine years, the Vandenbergs are selling to the co-op — and the co-op is keeping the butcher counter.',
            'image' => '/assets/img/photo-04.svg', 'image_caption' => 'Main Street, Trochu, on delivery morning.', 'image_credit' => 'File photo',
            'featured' => 0, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'trochu, main street, retail',
            'body' => $p(
                'TROCHU — The handwritten sign went up in the window Monday: same hours, same staff, new owner as of September 1. After twenty-nine years, Martin and Elly Vandenberg are selling the Trochu Family Foods building and business to the regional co-operative.',
                'The store is the last full grocery on the forty-minute stretch between Trochu and Delia. When the IGA in Craigmyle closed in 2019, its customers drove here; when this store\'s future looked uncertain last winter, the co-op board started phoning.',
                'The co-op\'s general manager confirmed the parts people actually asked about: the butcher counter stays, the school lunch account stays, and delivery to seniors on Thursdays stays. "We didn\'t buy a building," she said. "We bought a route people already drive."',
                'The Vandenbergs are staying in town. Martin\'s retirement plan, as stated at the till on Tuesday, is to finally be a customer with opinions.'
            ),
        ],
        [
            'title' => 'Kneehill council votes 5–2 to keep the grader',
            'desk' => 'news', 'dateline' => 'Kneehill County', 'byline' => 'Dana Ruthven',
            'lede' => 'Contracting out gravel maintenance looked cheaper on paper. The paper did not include March.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-1 day 7 hours'),
            'tags' => 'kneehill, county council, roads',
            'body' => $p(
                'KNEEHILL COUNTY — Council voted 5–2 Tuesday to keep its own grader and operator for the northeast division rather than contract the work out, ending a debate that has run since the fall budget.',
                'The contractor\'s bid came in $47,000 a year under the county\'s own cost. The case against it came from the operations superintendent, who kept his presentation to one slide: response times from the past three spring breakups, county crew against contracted crews in the two divisions that switched in 2021.',
                'The county grader had roads open in an average of eleven hours after a storm. The contracted divisions averaged twenty-nine, because the contractor\'s machines start where the contractor\'s priorities are.',
                'Council kept the machine. As one councillor put it before the vote: "Cheaper is a March word until it snows in March."'
            ),
        ],
        [
            'title' => 'Rowley\'s one-night museum opens for its fortieth summer',
            'desk' => 'community', 'dateline' => 'Rowley', 'byline' => 'Wes Hartley',
            'lede' => 'Population eight, pizza night once a month, and a main street kept exactly as it was left.',
            'image' => '/assets/img/photo-05.svg', 'image_caption' => 'Rowley\'s grain elevators at last light.', 'image_credit' => 'Staff photo',
            'featured' => 0, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'rowley, museums, elevators',
            'body' => $p(
                'ROWLEY — The first pizza night of the summer goes Saturday, which means the town of eight people will briefly be a town of four hundred, and the museum that is actually a whole main street will be open until the last car leaves.',
                'Rowley stopped being a working town decades ago and decided, more or less on purpose, to become a kept one. The saloon, the church, the school and the trading post stand furnished as they were left, maintained by a community association that measures its budget in pizza sales.',
                'Forty years in, the model has outlasted fancier plans elsewhere. The three grain elevators — among the last wooden rows standing in the province — got new cladding on the north faces this spring, paid for by last season\'s twelve pizza nights and a donation jar that is, famously, an old cream can.',
                'Doors open at five. The association asks visitors to park along the tracks and to remember the town has no cell coverage, which the regulars consider a feature.'
            ),
        ],
        [
            'title' => 'The gravel budget is the real infrastructure debate',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'Ribbon cuttings get the cameras. Getting home in March gets you to the hospital, the school and the bin.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-2 days 9 hours'),
            'tags' => 'editorial, roads, budgets',
            'body' => $p(
                'Two councils in our coverage area spent their longest meetings of the season this month on, respectively, one culvert and one grader. It would be easy to file both under small-town politics. It would also be wrong.',
                'The provincial infrastructure conversation is conducted in ribbon cuttings: interchanges, arenas, anything that photographs well with a shovel. But the infrastructure that decides whether rural life is viable is duller and closer to the ground — the crossing that makes a farm reachable, the grader that opens a road before the school bus runs.',
                'Hanna\'s council spent ninety minutes deciding what a road owes the four families on it, and landed on an answer: passage, engineered properly, even at $86,000. Kneehill\'s kept a machine that costs more on paper because paper does not drive a school bus through a March drift.',
                'Neither decision will get a photograph. Both are the debate that matters. We would take a province full of ninety-minute culvert arguments over one more groundbreaking, and we suspect most of our readers, standing at the end of their own gravel, would too.'
            ),
        ],
        [
            'title' => 'A dry June ends with forty minutes of rain, and it mattered',
            'desk' => 'weather', 'dateline' => 'Three Hills', 'byline' => 'Post staff',
            'lede' => 'Eleven millimetres in one cell, nothing twenty kilometres east. Here is who got it and what is coming.',
            'image' => '/assets/img/photo-06.svg', 'image_caption' => 'The cell that broke the dry spell, looking northwest from Highway 21.', 'image_credit' => 'Reader photo',
            'featured' => 0, 'published' => $ago('-3 days 1 hour'),
            'tags' => 'weather, rain, forecast',
            'body' => $p(
                'THREE HILLS — The cell came through between 4:10 and 4:50 Sunday afternoon and dropped eleven millimetres on a band roughly from Linden to Morrin. East of Highway 56 gauges caught nothing at all.',
                'For fields inside the band, agronomists call the timing close to ideal: canola at the four-leaf stage, cereals tillering, and enough moisture to carry two weeks. Outside the band, the two-week clock that started in May keeps running.',
                'The week ahead: sun through Wednesday with highs near 24, wind out of the northwest at 30 km/h and gusting on the ridges, then a system Thursday that current models split on — the American model brings ten millimetres, the Canadian slides it south of the river.',
                'Overnight lows dip to 9 with frost risk in the valley bottoms Friday. The market feed on our front page updates at 4:15 p.m.; if it didn\'t load, reload — prices move on this forecast as much as anything.'
            ),
        ],
        [
            'title' => '850-pound steers are paying for the trucking again',
            'desk' => 'agriculture', 'dateline' => 'Drumheller', 'byline' => 'Dana Ruthven',
            'lede' => 'Thursday\'s sale cleared 402.50, up two dollars, and the buyers on the phone were all from feedlots south of the river.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-3 days 6 hours'),
            'tags' => 'cattle, auction, markets',
            'body' => $p(
                'DRUMHELLER — The Thursday sale ran 1,400 head and the number that matters came off the board just before noon: 850-pound steers at $402.50 a hundredweight, up two dollars on the week and forty on the year.',
                'What changed is not the cattle. It is the geography of the bidding. Half the serious money Thursday was on the phone from feedlots south of the river, which means the price now carries the trucking — a cost that for two years sat on the seller.',
                'The auction market\'s owner reads it as a supply story with no quick ending: "Everybody who could hold heifers back held them back. The cattle just aren\'t out there, and the buyers know their pens are empty."',
                'For cow-calf operations that hung on through the dry years and the $180 calves, the arithmetic finally runs the right direction. The advice from the ring was uncharacteristically direct: if you have grass, weigh your options; if you don\'t, this is the market you were waiting for.'
            ),
        ],
        [
            'title' => 'The Linden rink replaces its ice plant after thirty-one winters',
            'desk' => 'community', 'dateline' => 'Linden', 'byline' => 'June Kowalski',
            'lede' => 'The compressor that outlived three Zambonis retires in August. The fundraiser that replaced it took nineteen months and one very good pie auction.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-4 days 4 hours'),
            'tags' => 'linden, rinks, fundraising',
            'body' => $p(
                'LINDEN — The ice plant at the Linden arena has started its last summer. The replacement — modern, efficient, and roughly the price of a house — arrives in August, paid for without a dollar of debt.',
                'The compressor went in the year the rink\'s current president was born. It outlived three Zambonis, two roof repairs and every prediction of its death since 2015, kept alive by a refrigeration tech from Acme who has serviced it for free "out of respect."',
                'The nineteen-month fundraising drive tells you how these towns work: $118,000 of the total came in gifts under a hundred dollars. The single biggest line item was the fall pie auction, which cleared $9,400 and set what organizers believe is a county record for a saskatoon pie.',
                'First ice goes in mid-September. The old compressor is not going to scrap — it is going on a stand in the lobby, with a plaque, which everyone involved agrees it has earned.'
            ),
        ],
        [
            'title' => 'Special Areas board asks the province for a seat at the water table',
            'desk' => 'politics', 'dateline' => 'Hanna', 'byline' => 'Priya Chana',
            'lede' => 'Irrigation expansion is being planned for the river the dry country depends on, and the dry country wants it in writing.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-5 days 2 hours'),
            'tags' => 'water, special areas, irrigation',
            'body' => $p(
                'HANNA — The Special Areas Board has formally asked for a standing seat on the provincial panel studying irrigation expansion on the Red Deer River, arguing that the driest municipality in the province should not learn about water allocation from a news release.',
                'The request, sent last week and released Tuesday, is written in the board\'s usual plain register. It does not oppose expansion. It asks for one thing: that any new allocation state, in writing, where the water comes from in a year like 2021.',
                'The Special Areas exist because of water — carved out in 1938 when the homestead map met the actual rainfall. The board\'s chair put the history to work: "We are not predicting a drought. We are remembering one. There is a difference, and it is our job to know it."',
                'The ministry acknowledged the letter and said panel membership will be settled by fall. Municipalities along the river, for their part, have so far said nothing at all — which in irrigation politics is its own kind of statement.'
            ),
        ],
        [
            'title' => 'The seed plant at Carbon books its first full season since the rebuild',
            'desk' => 'business', 'dateline' => 'Carbon', 'byline' => 'June Kowalski',
            'lede' => 'Two years after the fire, the cleaning line is running double shifts and the wait list runs to March.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'published' => $ago('-6 days 5 hours'),
            'tags' => 'carbon, seed, rebuild',
            'body' => $p(
                'CARBON — The seed cleaning plant answered the question hanging over it since the 2024 fire: the rebuilt line is fully booked through March, running double shifts for the first time in the co-op\'s history.',
                'The fire could have ended the plant. Cleaning capacity has been consolidating toward the big corridor facilities for years, and the insurance settlement would have paid out members comfortably. The membership voted 61–4 to rebuild anyway, on the theory that a plant forty minutes closer is worth more than a cheque.',
                'The new line cleans a third faster and, more to the point for growers watching seed-borne disease move north, added a colour sorter — the first in the district. Half the winter\'s bookings specify it.',
                'The manager\'s summary at Tuesday\'s open house ran eight words, delivered from the loading dock: "Turns out people drive to what still works."'
            ),
        ],
    ];

    // Front-page placements for the sample paper: canola is the hero; the
    // culvert, policing and grocery stories fill the featured band.
    $placements = [
        'canola-contracts-move-early-as-growers-watch-a-dry-june' => 'hero',
        'ninety-minutes-on-one-culvert-how-hanna-council-decides-what-a-road-is-worth' => 'featured',
        'province-rewrites-the-rural-policing-grant-and-the-counties-do-the-math' => 'featured',
        'the-last-grocery-store-between-trochu-and-delia-changes-hands' => 'featured',
    ];

    $insPost = $pdo->prepare('INSERT INTO posts
        (title, slug, category_id, byline, dateline, lede, body, image, image_caption, image_credit,
         meta_description, status, is_featured, placement, published_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insMap = $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)');
    $selTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
    $insTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
    $insPT  = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');

    foreach ($stories as $s) {
        $slug = slugify($s['title']);
        $insPost->execute([
            $s['title'], $slug, $catId[$s['desk']], $s['byline'], $s['dateline'],
            $s['lede'], $s['body'], $s['image'], $s['image_caption'], $s['image_credit'],
            excerpt($s['lede'], 155), 'published', $s['featured'], $placements[$slug] ?? '',
            $s['published'], $s['published'], $s['published'],
        ]);
        $postId = pp_seed_last_id($pdo, 'posts');
        $insMap->execute([$postId, $siteId]);
        foreach (array_filter(array_map('trim', explode(',', $s['tags'] ?? ''))) as $name) {
            $tslug = slugify($name);
            $selTag->execute([$tslug]);
            $tag = $selTag->fetch();
            $tagId = $tag ? (int) $tag['id'] : null;
            if ($tagId === null) {
                $insTag->execute([$name, $tslug]);
                $tagId = pp_seed_last_id($pdo, 'tags');
            }
            $insPT->execute([$postId, $tagId]);
        }
    }
}
