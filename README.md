WikiText-Back-End
=================

This is the back end PHP code used to power WikiText (http://www.wikitext.co.uk), run every time an SMS is sent to the Twilio number associated with the text.php file. The code is designed to send an SMS with the content from the relevant Wikipedia article to the number from which the original request was sent, although it can easily be adapted to serve another purpose involving returning the first 160 characters of a Wikipedia article based on a search term.

Please do play with and improve the code, as while it works the majority of the time, not all search terms return content when a relevant Wikipedia article is in fact available.

Note: This is my first time GitHub-ing, so if you think I have left out anything important from the README, let me know.
