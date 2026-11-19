DROP DATABASE IF EXISTS tourist_planner;
CREATE DATABASE tourist_planner CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE tourist_planner;

CREATE TABLE USER (
  UserID INT AUTO_INCREMENT PRIMARY KEY,
  Username VARCHAR(30) NOT NULL UNIQUE,
  FullName VARCHAR(100) NOT NULL,
  PasswordHash VARCHAR(255) NOT NULL,
  UserType ENUM('Tourist','Local','Admin') NOT NULL DEFAULT 'Tourist',
  CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ATTRACTION (
  AttractionID INT AUTO_INCREMENT PRIMARY KEY,
  Name VARCHAR(150) NOT NULL,
  Category VARCHAR(100) NOT NULL,
  Location VARCHAR(150) NULL,
  DistanceKm DECIMAL(5,1) NULL,
  Latitude DECIMAL(9,6) NULL,
  Longitude DECIMAL(9,6) NULL,
  MapQuery VARCHAR(255) NULL,
  OpeningHours VARCHAR(150) NULL,
  OpenTime TIME NULL,
  CloseTime TIME NULL,
  Fees DECIMAL(8,2) NULL,
  VisitMinutes INT NOT NULL DEFAULT 60,
  BestTime VARCHAR(150) NULL,
  Description TEXT NULL,
  Parking VARCHAR(255) NULL,
  Facilities VARCHAR(255) NULL,
  SafetyInfo VARCHAR(500) NULL,
  Accessibility VARCHAR(255) NULL,
  Rules VARCHAR(500) NULL
);

CREATE TABLE PHOTO (
  PhotoID INT AUTO_INCREMENT PRIMARY KEY,
  AttractionID INT NOT NULL,
  Path VARCHAR(500) NOT NULL,
  SortOrder INT NOT NULL DEFAULT 0,
  FOREIGN KEY (AttractionID) REFERENCES ATTRACTION(AttractionID) ON DELETE CASCADE
);

CREATE TABLE REVIEW (
  ReviewID INT AUTO_INCREMENT PRIMARY KEY,
  UserID INT NOT NULL,
  AttractionID INT NOT NULL,
  Rating TINYINT NOT NULL,
  Comment TEXT NULL,
  Status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (UserID) REFERENCES USER(UserID) ON DELETE CASCADE,
  FOREIGN KEY (AttractionID) REFERENCES ATTRACTION(AttractionID) ON DELETE CASCADE
);

CREATE TABLE ITINERARY (
  ItineraryID INT AUTO_INCREMENT PRIMARY KEY,
  UserID INT NOT NULL,
  Title VARCHAR(80) NOT NULL,
  StartTime TIME NOT NULL DEFAULT '09:00:00',
  HoursAvailable DECIMAL(3,1) NOT NULL DEFAULT 8.0,
  DateCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (UserID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE ITINERARY_ATTRACTION (
  ItineraryID INT NOT NULL,
  AttractionID INT NOT NULL,
  VisitOrder INT NOT NULL,
  PRIMARY KEY (ItineraryID, AttractionID),
  FOREIGN KEY (ItineraryID) REFERENCES ITINERARY(ItineraryID) ON DELETE CASCADE,
  FOREIGN KEY (AttractionID) REFERENCES ATTRACTION(AttractionID) ON DELETE CASCADE
);

INSERT INTO USER (Username, FullName, PasswordHash, UserType)
VALUES ('admin', 'System Administrator', '$2y$12$ugJAuNdGEUkYP853olngFOYpm80cqov8ZVm4j2EDUOs2cSpF1WHRK', 'Admin');

INSERT INTO ATTRACTION (Name, Category, Location, DistanceKm, Latitude, Longitude, MapQuery, OpeningHours, OpenTime, CloseTime, Fees, VisitMinutes, BestTime, Description, Parking, Facilities, SafetyInfo, Accessibility, Rules) VALUES
('Mount Lavinia Beach','Nature/Leisure','Mount Lavinia',10.8,6.831000,79.863000,'Mount Lavinia Beach, Sri Lanka','Open all day',NULL,NULL,0,90,'Late afternoon for the sunset','Famous seaside destination with sunsets, seafood and family outings.','Street parking and hotel car parks nearby; busy at weekends.','Seafood restaurants, shops and toilets close to the beach.','Take care when swimming. Currents can be strong, especially in the monsoon season. Keep valuables with you.','Sandy surface; limited wheelchair access. Family friendly.','Keep the beach clean. Wear modest clothing away from the water.'),
('Dehiwala Zoological Gardens','Nature','Dehiwala',9.4,6.857000,79.875800,'National Zoological Gardens Dehiwala, Sri Lanka','Daily, about 8:30 AM - 6:00 PM','08:30:00','18:00:00',NULL,150,'Morning, when the animals are most active','Zoo with diverse wildlife, elephant shows and educational exhibits.','On-site car park.','Cafeteria, toilets and shaded rest areas.','Keep a safe distance from enclosures and hold young children by the hand. Follow staff instructions.','Mostly paved paths with some slopes; suitable for families.','Do not feed or tease the animals. Avoid flash photography near animals.'),
('Sri Sumanarama Purana Viharaya','Religious','Piliyandala area',5.3,6.802000,79.922000,'Sri Sumanarama Purana Viharaya, Piliyandala, Sri Lanka','Daily; temple hours',NULL,NULL,0,45,'Early morning or evening','Historic Buddhist temple with cultural heritage and peaceful surroundings.','Limited space near the temple entrance.','Basic facilities; bring water.','Quiet and safe during the day; avoid visiting alone after dark.','Steps at some points; limited wheelchair access.','Dress modestly with shoulders and knees covered. Remove shoes and hats before entering shrine rooms. Never pose with your back to a Buddha statue.'),
('Diyatha Uyana Park','Leisure','Battaramulla',12.8,6.899900,79.918000,'Diyatha Uyana, Battaramulla, Sri Lanka','Daytime to late evening',NULL,NULL,NULL,90,'Evening, for the lake views','Lakeside park with walking paths, food stalls and family-friendly spaces.','Public car park nearby; fills quickly at weekends.','Food stalls, toilets and seating areas.','Crowded on weekends and public holidays; keep an eye on children near the water.','Paved walkways; mostly wheelchair friendly.','Keep the park clean and use the bins provided.'),
('Independence Square','Heritage','Colombo 7',15.1,6.901800,79.867800,'Independence Memorial Hall, Colombo, Sri Lanka','Open daily',NULL,NULL,0,45,'Morning or evening, when it is cooler','Landmark commemorating Sri Lanka''s independence, with gardens and the memorial hall.','Roadside parking nearby.','Gardens and seating; cafes in the surrounding area.','Busy roads around the square; cross carefully.','Level paths; mostly wheelchair friendly.','Behave respectfully around the memorial. Photography is generally allowed outdoors.'),
('Colombo National Museum','Cultural','Colombo 7',16.4,6.910200,79.861200,'National Museum of Colombo, Sri Lanka','Daily, about 9:00 AM - 5:00 PM (may close on some holidays)','09:00:00','17:00:00',NULL,120,'Weekday mornings are quieter','Largest museum in the country, showcasing artifacts, royal regalia and folk culture.','Parking available near the museum.','Toilets, a shop and a cafe in the area.','Bags may be checked at the entrance.','Steps at the entrance; ask staff about ramp or lift access.','Photography may be restricted inside some galleries. Follow staff instructions.'),
('Gangaramaya Temple','Religious/Cultural','Colombo 2',16.3,6.916700,79.856600,'Gangaramaya Temple, Colombo, Sri Lanka','Open daily; early morning to evening',NULL,NULL,NULL,75,'Morning, before it gets busy','Iconic Buddhist temple with a museum, festivals and scenic lake surroundings.','Limited parking; consider a taxi or tuk-tuk.','Museum, shop and toilets on site.','Watch your belongings in crowded areas.','Some steps and uneven floors; partial wheelchair access.','Dress modestly with shoulders and knees covered. Remove shoes before entering. Ask before taking photos of people.'),
('Viharamahadevi Park','Nature/Leisure','Colombo 7',15.8,6.914700,79.861600,'Viharamahadevi Park, Colombo, Sri Lanka','Open daily; daytime',NULL,NULL,0,60,'Early morning or late afternoon','Colombo''s largest public park with gardens, fountains and a golden Buddha statue.','Roadside parking near the park gates.','Shaded paths, benches and play areas.','Busy at weekends; keep belongings close.','Paved paths; mostly wheelchair friendly.','Keep to the paths and do not litter.'),
('Galle Face Green','Leisure','Colombo 3',17.3,6.927100,79.845000,'Galle Face Green, Colombo, Sri Lanka','Open all day',NULL,NULL,0,60,'Sunset, when it fills with families and kite flyers','Seaside promenade famous for sunsets, kite flying and street food.','Roadside parking along the green; crowded in the evening.','Street food stalls, benches and sea views.','Crowded at sunset; keep valuables safe and be careful near the sea wall.','Flat promenade; wheelchair friendly.','Keep the promenade clean. Check food stalls for cleanliness.'),
('Bellanwila Rajamaha Viharaya','Religious','Bellanwila',6.5,6.819800,79.887200,'Bellanwila Rajamaha Viharaya, Sri Lanka','Daily; temple hours',NULL,NULL,0,45,'Early morning or poya (full moon) days for the atmosphere','Revered temple with a sacred Bodhi tree and the annual Esala Perahera festival.','Temple car park; very busy on poya days.','Toilets and offering stalls nearby.','Expect big crowds on poya days; keep children close.','Paved grounds; some steps at shrines.','Dress in white or modest clothing. Remove shoes and hats. Do not point your feet toward Buddha statues.'),
('Baddegana Wetland Park','Nature','Pita Kotte',13.1,6.885000,79.905000,'Baddegana Wetland Park, Pita Kotte, Sri Lanka','Daytime',NULL,NULL,NULL,75,'Early morning for birdwatching','Wetland sanctuary with birdwatching trails, biodiversity and eco-tourism value.','Small car park near the entrance.','Walking trails and viewing spots; bring water.','Wear insect repellent and watch your step on wet paths.','Natural trails; limited wheelchair access.','Stay on the marked trails and keep noise low near the birds.'),
('Diyasaru Park','Nature','Thalawathugoda',11.7,6.880000,79.924000,'Diyasaru Park, Thalawathugoda, Sri Lanka','Daytime',NULL,NULL,NULL,75,'Early morning or late afternoon','Urban wetland park with walking paths, bird habitats and conservation exhibits.','Parking available at the entrance.','Walking paths, bird-watching points and exhibits.','Wear insect repellent and avoid the water edges.','Paved paths in parts; mostly accessible.','Do not litter or disturb wildlife.');
