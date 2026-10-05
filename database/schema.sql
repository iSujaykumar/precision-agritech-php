-- Import into an empty Hostinger MySQL database. Do not open this file in the browser.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'customer',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  phone_verified_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  password_changed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY users_email (email),
  UNIQUE KEY users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  line VARCHAR(400) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state_name VARCHAR(80) NOT NULL,
  postal_code VARCHAR(12) NOT NULL,
  KEY addresses_user (user_id),
  CONSTRAINT addresses_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL,
  UNIQUE KEY categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL,
  sku VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  scientific_name VARCHAR(160) NOT NULL,
  variety VARCHAR(160) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  short_description VARCHAR(800) NOT NULL,
  description TEXT NOT NULL,
  planting_info TEXT NOT NULL,
  care_info TEXT NOT NULL,
  flowering_info TEXT NOT NULL,
  colour VARCHAR(160) NOT NULL,
  uses VARCHAR(400) NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  unit_label VARCHAR(80) NOT NULL,
  price_inr INT NOT NULL,
  compare_at_inr INT NULL,
  stock_qty INT NOT NULL,
  reserved_qty INT NOT NULL DEFAULT 0,
  min_order INT NOT NULL DEFAULT 1,
  availability VARCHAR(20) NOT NULL,
  season_label VARCHAR(160) NOT NULL,
  bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  on_offer TINYINT(1) NOT NULL DEFAULT 0,
  offer_starts_at DATETIME NULL,
  offer_ends_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY products_slug (slug),
  UNIQUE KEY products_sku (sku),
  CONSTRAINT products_category_fk FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL,
  lookup_token VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_email VARCHAR(160) NOT NULL,
  status VARCHAR(30) NOT NULL,
  payment_status VARCHAR(30) NOT NULL,
  payment_method VARCHAR(30) NOT NULL,
  inventory_state VARCHAR(20) NOT NULL,
  subtotal_inr INT NOT NULL,
  discount_inr INT NOT NULL,
  shipping_inr INT NOT NULL,
  total_inr INT NOT NULL,
  address_line VARCHAR(400) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state_name VARCHAR(80) NOT NULL,
  postal_code VARCHAR(12) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY orders_number (order_number),
  UNIQUE KEY orders_token (lookup_token),
  CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  sku VARCHAR(40) NOT NULL,
  unit_label VARCHAR(80) NOT NULL,
  unit_price_inr INT NOT NULL,
  quantity INT NOT NULL,
  line_total_inr INT NOT NULL,
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE order_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL,
  note VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT order_events_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  author_name VARCHAR(80) NOT NULL,
  rating TINYINT NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY reviews_one (product_id, user_id),
  CONSTRAINT reviews_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT reviews_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  amount INT NOT NULL,
  min_order_inr INT NOT NULL DEFAULT 0,
  usage_limit INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE coupon_redemptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  customer_email VARCHAR(160) NOT NULL,
  amount_inr INT NOT NULL,
  CONSTRAINT coupon_redemptions_coupon_fk FOREIGN KEY (coupon_id) REFERENCES coupons(id),
  CONSTRAINT coupon_redemptions_order_fk FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  handled_at TIMESTAMP NULL,
  staff_note VARCHAR(400) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE wholesale_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(160) NOT NULL,
  location VARCHAR(160) NOT NULL,
  products VARCHAR(255) NOT NULL,
  quantity VARCHAR(80) NOT NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  staff_note VARCHAR(400) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE settings (
  `key` VARCHAR(80) NOT NULL PRIMARY KEY,
  `value` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  identifier VARCHAR(160) NOT NULL DEFAULT '',
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY login_attempts_ip (ip, attempted_at),
  KEY login_attempts_identifier (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE lookup_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY lookup_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE mobile_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  provider_sid VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  verified_at TIMESTAMP NULL,
  ip VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  KEY mobile_verifications_phone (phone, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY password_resets_hash (token_hash),
  CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE inventory_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  stock_before INT NOT NULL,
  stock_after INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY inventory_movements_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admin_audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  before_text VARCHAR(500) NULL,
  after_text VARCHAR(500) NULL,
  ip VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET FOREIGN_KEY_CHECKS=1;
INSERT INTO categories (id, slug, name, sort_order) VALUES
(1, 'bedding', 'Bedding annuals', 1),
(2, 'winter', 'Winter colour', 2),
(3, 'shade', 'Shade beds', 3),
(4, 'specialty', 'Specialty', 4);
INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, compare_at_inr, stock_qty, min_order, availability, season_label, bestseller, is_new, on_offer) VALUES
('marigold','PA-MAR-104','Marigold','Tagetes erecta','African mix',1,'Sturdy African marigold trays for beds, borders and garlands. Sold by the tray.','Nursery-raised marigold seedlings from Theur, ready for the winter and summer colour beds. The tray is the unit of sale — not single plants.','Space plants 20–25 cm apart in full sun after the tray has hardened.','Water at the base. Pinch the first bud if you want a bushier plant.','About 6–8 weeks from transplant, then continuously if deadheaded.','Yellow, gold, orange','Beds, borders, garlands, farm colour','/products/marigold.webp','tray (104 plants)',220,280,86,1,'in_stock','Year-round in Pune, strongest October–March',1,0,1),
('petunia','PA-PET-104','Petunia','Petunia hybrida','Spreading mix',2,'Spreading petunia for pots, hanging baskets and front beds through the cool season.','Compact petunia trays grown for winter colour in Pune. One tray is 104 plants. Large lots are confirmed by phone.','Transplant into full sun. Keep the crown above the soil line.','Feed lightly every two weeks. Do not let pots dry out completely.','Continuous through the cool months once established.','Pink, purple, white, red','Pots, baskets, front of beds','/products/petunia.webp','tray (104 plants)',260,NULL,64,1,'seasonal','October to February',1,0,0),
('vinca','PA-VIN-104','Vinca','Catharanthus roseus','Heat-tolerant mix',1,'Heat-tolerant vinca trays for sunny beds once the weather warms.','Madagascar periwinkle seedlings raised for landscapers who need a long-lasting sunny bed. Sold only by the tray.','Full sun, well-drained soil. Space 15–20 cm.','Avoid waterlogged beds. Established plants tolerate dry spells.','Continuous in warm weather.','Rose, white, blush','Roadside beds, farm borders, pots','/products/vinca.webp','tray (104 plants)',240,NULL,72,1,'in_stock','Warm months, available to book ahead',1,0,0),
('pansy','PA-PAN-104','Pansy','Viola wittrockiana','Clear-face mix',2,'Cool-season pansy trays for winter beds and pots around Pune.','Pansy is a winter crop at Theur. Trays are listed while the season is open. Prices are starting rates per tray.','Morning sun, afternoon shade if the week is hot. Space 15 cm.','Keep evenly moist. Remove spent blooms.','Through the cool season.','Blue, yellow, white, bicolour','Winter beds, pots, edging','/products/pansy.webp','tray (104 plants)',250,NULL,58,1,'seasonal','October to February',1,0,0),
('geranium','PA-GER-104','Geranium','Pelargonium hortorum','Zonal',4,'Zonal geranium trays for pots and bright verandas.','Geranium seedlings for retail pots and landscape containers. The count on the tray is 104 young plants.','Bright light, not harsh afternoon sun in April. Space 20 cm in pots.','Let the top of the mix dry slightly between waterings.','Clusters through the cool and mild months.','Red, salmon, white','Pots, verandas, gift plants','/products/geranium.webp','tray (104 plants)',360,NULL,34,1,'in_stock','Best October–March',1,0,0),
('zinnia','PA-ZIN-104','Zinnia','Zinnia elegans','Cut-flower mix',1,'Cut-flower zinnia trays. Bright heads for beds and small bunches.','Zinnia trays for farms and florists who want a fast summer colour crop. Sold by the tray, not as loose plants.','Full sun after the soil is warm. Space 20 cm.','Water the soil, not the leaves, to keep mildew down.','About 7 weeks from transplant.','Pink, orange, red, lime','Cut flowers, beds','/products/zinnia.webp','tray (104 plants)',210,260,48,1,'in_stock','Warm season',0,0,1),
('celosia','PA-CEL-104','Celosia','Celosia argentea','Plume',1,'Plume celosia for hot-colour beds and dried bunches.','Celosia seedlings raised for landscapers who need a strong accent in sunny beds. Price is per tray.','Full sun, rich but draining soil. Space 15–20 cm.','Do not overwater once established.','Plumes hold for weeks and dry well.','Red, orange, gold','Accent beds, drying','/products/celosia.webp','tray (104 plants)',230,290,40,1,'in_stock','Warm season',0,0,1),
('sunflower','PA-SUN-104','Sunflower','Helianthus annuus','Dwarf branching',1,'Dwarf branching sunflower trays for beds, not the giant field type.','A shorter, branching sunflower so trays suit garden beds and farm gates. Still sold only as a full tray.','Full sun. Give 25 cm if you want side stems.','Stake only if the site is very windy. Water deeply.','About 8 weeks from transplant.','Gold','Beds, farm entrances, small bunches','/products/sunflower.webp','tray (104 plants)',340,400,28,1,'in_stock','Warm season',0,0,1),
('antirrhinum','PA-ANT-104','Antirrhinum','Antirrhinum majus','Intermediate snapdragon',2,'Snapdragon trays for winter spikes in beds and bunches.','Intermediate antirrhinum from the Theur nursery, timed for the cool season around Pune.','Sun to light shade. Space 15 cm. Pinch once for branching.','Even moisture. Good air movement.','Spikes through winter.','Pink, white, yellow, red','Winter beds, cut spikes','/products/antirrhinum.webp','tray (104 plants)',270,NULL,44,1,'seasonal','October to February',0,0,0),
('dianthus','PA-DIA-104','Dianthus','Dianthus chinensis','China pink',2,'China pink trays for scented winter edging.','Dianthus seedlings for edging and pots in the cool season. Catalogue price is the starting rate per tray.','Full sun, sharp drainage. Space 15 cm.','Avoid wet crowns overnight.','Fringed blooms through the cool months.','Pink, crimson, white','Edging, pots','/products/dianthus.webp','tray (104 plants)',255,NULL,36,1,'seasonal','October to February',0,0,0),
('cineraria','PA-CIN-104','Cineraria','Pericallis hybrida','Daisy mix',2,'Cool-season cineraria for shaded winter pots.','Cineraria likes the cool, bright shade of a Pune winter. Trays are listed while that window is open.','Bright shade, never hot afternoon sun. One plant per 15 cm pot, or 20 cm in beds.','Keep moist. Do not splash the leaves late in the day.','Daisy heads in mid to late winter.','Blue, magenta, white, bicolour','Shade pots, winter display','/products/cineraria.webp','tray (104 plants)',300,NULL,22,1,'seasonal','November to February',0,1,0),
('lobelia','PA-LOB-104','Lobelia','Lobelia erinus','Trailing',2,'Trailing lobelia to spill from winter baskets.','Small blue lobelia for the edge of a basket or a winter pot. The tray holds 104 plugs.','Cool weather, morning sun. Tuck along the rim of a basket.','Do not let the plug dry out after transplant.','Through the cool season.','Blue','Baskets, pot edges','/products/lobelia.webp','tray (104 plants)',245,NULL,30,1,'seasonal','October to February',0,0,0),
('gazania','PA-GAZ-104','Gazania','Gazania rigens','Treasure mix',2,'Sun-loving gazania trays. Flowers open on bright days.','Gazania for dry, sunny winter beds. A practical tray for landscapers who cannot water every day.','Full sun, lean soil. Space 20 cm.','Established plants dislike wet feet.','Opens in sun through the cool season.','Orange, yellow, bronze','Dry beds, slopes, pots','/products/gazania.webp','tray (104 plants)',275,NULL,26,1,'seasonal','October to March',0,0,0),
('salvia','PA-SAL-104','Salvia','Salvia splendens','Scarlet',2,'Scarlet salvia spikes for winter and early summer beds.','Salvia trays from Theur for a strong red line in a formal bed. Sold by the tray.','Sun. Space 20 cm. Pinch once if the plants are leggy.','Regular water while they are establishing.','Upright spikes over a long period.','Scarlet','Formal beds, contrast lines','/products/salvia.webp','tray (104 plants)',265,NULL,38,1,'seasonal','October to March',0,0,0),
('begonia','PA-BEG-104','Begonia','Begonia semperflorens','Wax, green leaf',3,'Wax begonia trays for bright shade and morning-sun beds.','Wax begonia is the shade workhorse. These trays are for landscapers planting under trees or on east walls.','Bright shade or morning sun. Space 15 cm.','Even moisture. Avoid hot midday sun.','Nearly continuous in mild weather.','Pink, white, red','Shade beds, pots','/products/begonia.webp','tray (104 plants)',320,NULL,33,1,'in_stock','Mild months, shade sites year-round',0,0,0),
('impatiens','PA-IMP-104','Impatiens','Impatiens walleriana','Shade mix',3,'Impatiens trays for deep colour in shade.','Busy Lizzie seedlings for shaded resort beds and home courtyards. One tray, 104 plants.','Shade to dappled light. Space 15–20 cm.','Never let the bed dry hard. Protect from hot wind.','Continuous while the weather stays mild.','Pink, coral, white, violet','Shade beds, courtyards','/products/impatiens.webp','tray (104 plants)',280,NULL,29,1,'in_stock','Mild and monsoon shade',0,0,0),
('chrysanthemum','PA-CHR-104','Chrysanthemum','Chrysanthemum morifolium','Garden mum',4,'Garden chrysanthemum trays for autumn pots and beds.','Garden mums raised as young plants, not forced pot mums. Book larger seasonal lots with the nursery office.','Sun, rich soil. Space 25 cm. Pinch until late monsoon if you want a bush.','Feed while they are growing. Stake tall varieties.','Autumn heads.','Yellow, bronze, white, pink','Autumn beds, pots, loose bunches','/products/chrysanthemum.webp','tray (104 plants)',380,NULL,18,1,'in_stock','Plant ahead of autumn flowering',0,0,0),
('pentas','PA-PEN-104','Pentas','Pentas lanceolata','Star cluster',4,'Pentas trays. Star clusters that keep flowering in warmth.','Pentas for sunny landscape beds that need a nectar plant and a long season. Sold by the tray.','Full sun. Space 25 cm.','Regular water in summer. Trim lightly to keep it compact.','Clusters over a long warm season.','Red, pink, white, lavender','Sunny beds, butterfly gardens','/products/pentas.webp','tray (104 plants)',290,NULL,24,1,'in_stock','Warm season',0,1,0),
('platycodon','PA-PLA-104','Platycodon','Platycodon grandiflorus','Balloon flower',4,'Balloon flower trays — a slower specialty, not a bedding filler.','Platycodon seedlings for growers who want a perennial-style balloon flower. Still dispatched as a seedling tray.','Sun to light shade, soil that drains. Do not disturb the root once planted.','Moderate water. It is slow to start, then steady.','Balloon buds open to star bells in the mild season.','Blue','Specialty beds, pots','/products/platycodon.webp','tray (104 plants)',420,NULL,12,1,'in_stock','Book ahead',0,1,0),
('ptilotus','PA-PTI-104','Ptilotus','Ptilotus exaltatus','Joey',4,'Ptilotus trays. Feathery pink heads for dry, bright sites.','A specialty tray for designers who want something other than the usual bedding line. Price is per tray of 104.','Full sun, very sharp drainage. Space 20 cm.','Keep it on the dry side once established.','Feathery cones over a long stretch.','Pink','Dry modern beds, pots','/products/ptilotus.webp','tray (104 plants)',450,NULL,10,1,'in_stock','Mild, dry weather',0,1,0);
INSERT INTO settings (`key`, `value`) VALUES
('shipping_flat_inr','180'),
('free_shipping_over_inr','4000'),
('tax_percent','0');
