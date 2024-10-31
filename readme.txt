=== My Two Cents ===
Contributors: meitar
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=BitCoin%20Comments&item_number=BitCoin%20Comments&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: BitCoin, comments, moderation, cryptocurrency, monetize, money, monetization, spam
Requires at least: 3.0
Tested up to: 4.0
Stable tag: 0.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Get BitCoin from commenters. Auto-approve comments that include a BitCoin donation. Fight spam with BitCoin microtransactions.

== Description ==

My Two Cents integrates your WordPress-powered site or blog's commenting features with BitCoin.

My Two Cents is extremely lightweight, completely automated, and extremely unobtrusive. Simply provide your BitCoin address(es) and the plugin takes care of the rest. For best anti-spam results, I recommend enabling WordPress's built-in "Comment must be manually approved" option in its [Discussion Settings](https://codex.wordpress.org/Settings_Discussion_Screen).

By default, My Two Cents automatically generates a unique BitCoin address for each comment. This feature relies on the "low-trust" [BlockChain.info Receive Payments API](https://blockchain.info/api/api_receive). Your commenters send BTC to the automatically generated addresses, and BlockChain.info forwards the amount to the address that you choose.

Optionally, you can also use the simpler "transaction polling" method. This will add a field to your comment form asking for the commenter's BitCoin address. Once each hour, My Two Cents queries the BitCoin blockchain (the public accounting ledger of transactions) and searches for funds transfers to your address from the commenter's address.

When a valid transaction is detected, My Two Cents acts on the associated comment(s).

== Installation ==

1. Upload `my-two-cents` to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Enter your BitCoin address(es) in the plugin's Settings screen.

== Frequently Asked Questions ==

= How do I get a BitCoin address? =

If you don't have any BitCoin addresses, you probably need to get a *wallet*. A BitCoin wallet is a software program that generates BitCoin addresses for you in the form of specially-linked random numbers called private and public keys. To learn more about getting an address, see the [BitCoin Foundation's "Getting started with BitCoin" guide](https://bitcoin.org/en/getting-started).

== Screenshots ==

1. When you first install My Two Cents, you'll need to enter one or more of your receiving addresses. Other options allow you to tweak display and security options such as the size of the generated QR code image and the number of network confirmations required before a transaction is considered valid.

2. When a comment is submitted, My Two Cents offers a direct clickable link and a QR code with which the commenter can send you BitCoin. Within the hour of their BitCoin transaction being confirmed on the blockchain, My Two Cents will approve their comment automatically.

3. Optionally, My Two Cents can also use "transaction polling," a method for detecting transactions without the use of automatically-generated forarding addresses. If you choose to enable this feature, My Two Cents adds a "BitCoin address" field to your comment forms. Commenters provide their BitCoin address here to register their "My Two Cents."

== Change log ==

= Version 0.2 =

* Feature: Automatically generate individual receive addresses per comment.
* Feature: Optionally set the minimum number of confirmations you require to consider a transaction valid.
* Usability: Commenters no longer need to enter their own BTC addresses before posting comments.

= Version 0.1.1 =

* Usability: Validate BTC addresses and automatically reject invalid ones.

= Version 0.1 =

* Initial release.

== Other notes ==

Maintaining this plugin is a labor of love. However, if you like it, please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=meitarm%40gmail%2ecom&lc=US&item_name=BitCoin%20Comments%20WordPress%20Plugin&item_number=bitcoin%2dcomments&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted) for your use of the plugin, [purchasing one of Meitar's web development books](http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fs%3Fie%3DUTF8%26redirect%3Dtrue%26sort%3Drelevancerank%26search-type%3Dss%26index%3Dbooks%26ref%3Dntt%255Fathr%255Fdp%255Fsr%255F2%26field-author%3DMeitar%2520Moscovitz&tag=maymaydotnet-20&linkCode=ur2&camp=1789&creative=390957) or, better yet, contributing directly to [Meitar's Cyberbusking fund](http://Cyberbusking.org/). (Publishing royalties ain't exactly the lucrative income it used to be, y'know?) Your support is appreciated!
