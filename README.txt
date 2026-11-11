NETMONITOR TMS - NETWORK TRAFFIC MONITORING SYSTEM
Net Monitor Ltd, Kisumu            Developer: Beryl Atieno
=====================================================================

WHAT IS IN THIS FOLDER
  database.sql   the database (tables + starter users, devices, settings)
  db.php         database connection details and the device secret key
  functions.php  helper functions (settings, offline check, dashboard figures)
  auth.php       blocks pages from people who are not logged in
  login.php / logout.php
  dashboard.php  main screen          live.php     live traffic monitor
  devices.php    add/edit/delete      alerts.php   thresholds + alert log
  search.php     search the logs      reports.php  reports (Export PDF = print to PDF)
  collect.php    where devices send their readings every 5 seconds
  api.php        JSON data used for the 5-second refresh
  simulate.php   pretends to be the network devices (for demos)
  header.php / footer.php / assets/   page layout, styling and the chart code

LOGINS (change them after the first login)
  Administrator:  admin    / admin123
  Manager:        manager / manager123     (can see the Dashboard and Reports only)


PART 1 - RUN IT ON YOUR LAPTOP WITH XAMPP
  1. Install XAMPP, open the XAMPP Control Panel and Start "Apache" and "MySQL".
  2. Copy the whole "netmonitor" folder into  C:\xampp\htdocs\
  3. Open  http://localhost/phpmyadmin  ->  click "Import" -> choose database.sql -> "Import".
     (This creates the database called  netmonitor.)
  4. Open db.php in Notepad. With XAMPP you do not need to change anything.
     Optional: change COLLECT_KEY to your own secret word.
  5. Open  http://localhost/netmonitor/  and log in as admin / admin123.
  6. To see live data, open Command Prompt and run:
        C:\xampp\php\php.exe C:\xampp\htdocs\netmonitor\simulate.php
     Leave that window open. Within seconds the dashboard, live traffic page,
     alerts and reports start filling with readings. Press Ctrl+C to stop.
     (If you changed COLLECT_KEY in db.php, simulate.php uses it automatically.)


PART 2 - PUT IT ONLINE WITH FREE PHP + MYSQL HOSTING (e.g. InfinityFree)
  GitHub Pages and Netlify cannot run PHP, so use a PHP host.
  1. Create a free hosting account and a new website/account.
  2. In the control panel, open "MySQL Databases" and create a database.
     Write down the database name, username, password and the host name
     (it is NOT "localhost" on most free hosts, for example sql123.infinityfree.com).
  3. Open phpMyAdmin from the control panel, select your new database, click "Import"
     and upload database.sql.
     IMPORTANT: first open database.sql in Notepad and delete the first TWO lines that
     create/use the "netmonitor" database (CREATE DATABASE ... and USE netmonitor;),
     because on free hosting your database already exists and has its own name.
  4. Edit db.php and put in the host, database name, username and password from step 2.
     Change COLLECT_KEY to your own secret word.
  5. Upload all the files (keep the assets folder) into the "htdocs" folder using the
     File Manager or an FTP program.
  6. Open your site address, for example  http://yourname.infinityfreeapp.com/
     This address is the link to put in your project document.
  7. To show data on the online copy, run simulate.php on your laptop and point it at the
     online address:
        php simulate.php http://yourname.infinityfreeapp.com
     (simulate.php reads the device list from the database named in db.php, so for the
     online copy keep a copy of db.php that has the online database details, or add
     devices 1-6 and let the real devices send readings to collect.php.)


HOW REAL DEVICES SEND DATA
  Each device (or a small script on it) sends an HTTP POST to collect.php every 5 seconds with:
     key=<COLLECT_KEY>  device_id=<id>  bytes_in=<n>  bytes_out=<n>  bandwidth_pct=<0-100>
  Duplicate readings for the same device and second are rejected. A reading at or above the
  alert threshold raises a "Traffic spike" alert; a device silent for longer than the offline
  timeout is marked offline with a "Device offline" alert.


SECURITY NOTES
  - Passwords are stored as hashes (password_hash / password_verify).
  - All database queries use prepared statements.
  - Forms are protected with a token; pages check the user's role.
  - Change the default passwords and COLLECT_KEY before real use.
