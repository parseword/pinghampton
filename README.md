# pinghampton
Example polling API client daemon in core PHP, stashed here for later...

This code is part of a ping plotting application I poked around at writing. My 
goal was to implement a minimalistic client/server API using nothing but core 
PHP, without any external dependencies. Once the bare essentials were done, 
and I had a functional client "daemon" and a functional server-side ingester, I 
moved on and never got around to building a web interface. 

Everything here *works*, but nothing is complete. I've logged millions of pings 
with the client (`pinger.php`) running on a variety of Linux, Windows, Mac, and 
FreeBSD systems. But there's no web interface to speak of, just `display.php`, 
which can generate a graph of ping data. It uses JpGraph, not included here.

To run the client: 

`/usr/bin/php /usr/local/etc/pinghampton/pinger.php > /dev/null 2>&1 &`

On the server, a cron job should be set to run every few minutes and distill 
the submitted raw ping data into the format used by the grapher:

`*/3 * * * * /usr/bin/mysql --defaults-file=/home/cronsql/.mysql_password < /path/to/production-push.sql`
