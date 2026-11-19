=====================================================================
LOCAL TOURIST DAY-VISIT PLANNER AND INFORMATION SYSTEM

=====================================================================

---------------------------------------------------------------------
1. WHAT IS INSIDE THIS ZIP FILE
---------------------------------------------------------------------
Web Application Project/
  source-code/tourist-planner/   Complete front-end and back-end source code
                                 (HTML, CSS, JavaScript, PHP) and the uploads/
                                 folder that contains the attraction photos
  database/database.sql          Creates an empty database with the 12
                                 attractions and the admin account
  database/tourist_planner_export.sql
                                 Full copy of the database WITH the photo
                                 records (use this one to see the photos)
  diagrams/                      StarUML project file (.mdj) and the five
                                 diagrams exported as images:
                                 Use Case, Activity, Sequence, Class, ER


---------------------------------------------------------------------
2. WHAT THE SYSTEM DOES
---------------------------------------------------------------------
A login-based web application that helps tourists and local visitors plan
a one-day trip to attractions within 25 km of Piliyandala.

 - 12 attractions with category filter and search
 - Description, opening hours, entrance fee, visit time, parking,
   facilities, safety, accessibility, rules and photos
 - Map location (Google Maps) and live weather (Open-Meteo)
 - One-day itinerary planner: start time, hours available, estimated
   travel times, opening-hour warnings, "too long" warning, Smart order,
   save and reopen plans
 - Visitor reviews, approved by an administrator before publishing
 - Admin panel: add, edit and delete attractions, upload photos,
   moderate reviews, manage users
 (Booking and payment are not included, as specified.)

---------------------------------------------------------------------
3. REQUIREMENTS
---------------------------------------------------------------------
 - Windows, macOS or Linux computer
 - XAMPP (Apache + MySQL/MariaDB + PHP 8.x)  https://www.apachefriends.org
 - A web browser (Google Chrome recommended)
 - Internet connection (needed for the map and the weather panel only)

---------------------------------------------------------------------
4. INSTALLATION (about 5 minutes)
---------------------------------------------------------------------
1) Install XAMPP and open the XAMPP Control Panel.
   Click START for both "Apache" and "MySQL" (both turn green).

2) Copy the folder
      source-code/tourist-planner
   into the XAMPP web folder, so that you have:
      C:\xampp\htdocs\tourist-planner\login.php
   (Keep the folder name "tourist-planner". If you rename it, change
    BASE_URL at the top of config/config.php.)

3) Open  http://localhost/phpmyadmin  in the browser.
   Click the "Import" tab, choose a file, then click "Import" at the bottom:
      - database/tourist_planner_export.sql   (recommended: includes photos)
        OR
      - database/database.sql                  (clean database, no photos)
   Either file creates the database "tourist_planner" automatically.
   If the export file reports "no database selected", first create an empty
   database named tourist_planner in phpMyAdmin, select it and import again.

4) Open  http://localhost/tourist-planner/
   The sign-in screen appears.

Database settings (config/config.php): host localhost, database
tourist_planner, user root, empty password (XAMPP defaults).

---------------------------------------------------------------------
5. LOGIN DETAILS FOR EVALUATION
---------------------------------------------------------------------
These are DEMO accounts for the local test system only (no real data).

 Administrator : username  admin      password  admin123
 Visitor       : click "Create an account" and register any username
                 (letters, numbers, underscore; password 6+ characters)

Passwords are stored as bcrypt hashes in the database.

---------------------------------------------------------------------
6. QUICK TEST GUIDE (follows the required features)
---------------------------------------------------------------------
 1. Homepage     : open the URL - the sign-in screen loads.
                   Register a visitor account and sign in.
 2. Attractions  : "Browse attractions" lists the 12 attractions.
 3. Filter       : choose a category (e.g. Religious) or search "temple".
 4. Details      : click "View details" - description, hours, fee, visit
                   time, parking, safety, accessibility, rules.
 5. Map          : the Google map shows the location; "Get directions"
                   opens Google Maps. Weather panel shows live weather.
 6. One-day plan : press "+ Itinerary" on 3 attractions, open
                   "My itinerary", press "Update plan" and "Smart order",
                   then "Save itinerary".
 7. Admin        : sign out, sign in as admin. Open "Manage attractions"
                   and add, edit and delete an attraction. Open
                   "Moderate reviews" to approve a review.
 8. Database     : http://localhost/phpmyadmin -> tourist_planner.

---------------------------------------------------------------------
7. TECHNOLOGIES USED
---------------------------------------------------------------------
 Front end : HTML5, CSS3, JavaScript (ES6, fetch API)
 Back end  : PHP 8 (PDO, sessions)
 Database  : MySQL / MariaDB
 Tools     : XAMPP, Visual Studio Code, phpMyAdmin, Git and GitHub, StarUML
 Services  : Google Maps (map embed and directions links),
             Open-Meteo (weather)

---------------------------------------------------------------------

