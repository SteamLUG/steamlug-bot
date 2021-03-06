===[ INDEX ]===================================================================

FEATURES
LICENSE
HOW TO START
FAQ

===[ FEATURES ]================================================================

[disabled] Checks for (and displays) new Steam group RSS feed entries.

Checks for (and displays) new SteamLUG RSS feed entries.

Gives title information about URLs (in $GLOBALS['needurlinfo']).

Provides the original poster's comment for Imgur images, if available.

Checks for (and displays) new Tweets.

Checks for (and displays) new game/DLC releases on Steam that support Linux.

Checks for (and displays) new Humble Bundles and Humble Weekly sales.

Replies to:
- !steamstatus (and !issteamdown and !issteamup)
- !help
- !events
- !time <"UTC"|two-letter country code|timezone name>
- !seen <IRC nick>
- !s <IRC nick> | !s set <Steam customURL /id/> | !s me
- !ping [text]
- !t <Twitter name> [1-3] | !t <Tweet id>
- !w <Wikipedia search>
- !id [IRC nick|name]
- !pcgw [game name]
- !game <game name>
- !mumble
- !msg tell <nick> <text> | !msg list | !msg show <number> | !msg delete <number>
- !question [word]
- !xkcd <number> | !xkcd <word> | !xkcd last
- CTCP queries: VERSION, PING <(milli)seconds>, SOURCE, URL, FINGER, TIME, CLIENTINFO, USERINFO
- Private chat: HELP, INFO

===[ LICENSE ]=================================================================

zlib License

Copyright (C) 2014-2015 SteamLUG (https://steamlug.org/)

This software is provided 'as-is', without any express or implied
warranty.  In no event will the authors be held liable for any damages
arising from the use of this software.

Permission is granted to anyone to use this software for any purpose,
including commercial applications, and to alter it and redistribute it
freely, subject to the following restrictions:

1. The origin of this software must not be misrepresented; you must not
claim that you wrote the original software. If you use this software
in a product, an acknowledgment in the product documentation would be
appreciated but is not required.
2. Altered source versions must be plainly marked as such, and must not be
misrepresented as being the original software.
3. This notice may not be removed or altered from any source distribution.

===[ HOW TO START ]============================================================

1. apt-get install mysql-server php5-cli php5-mysql php5-curl
2. Change the settings inside the "steamlug-bot_settings.php" file.
3. Create the required database and tables (see steamlug-bot.sql).
4. In the "get_data.sh: file, change the /var/www/steamlug-bot/ path.
5. Make sure get_data.sh is running; see comments inside that file.
6. Run: $ php steamlug-bot.php

If you have a webserver running, you can open log.php to see what
the steamlug-bot is doing (receiving and sending).

===[ FAQ ]=====================================================================

Q: What to do about: /usr/bin/php: No such file or directory
A: apt-get install php5-cli

Q: What to do about: Call to undefined function mysqli_init()
A: apt-get install php5-mysql

Q: What to do about: Use of undefined constant CURLOPT_HTTPHEADER
A: apt-get install php5-curl
