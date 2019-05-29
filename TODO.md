* Login Options
  * Single Password - string, hash created by secure login
  * ACL - string, json object a la
		{'username1': {
			'login_hash': 'asldkfjalskdfas',
			'chroot':	'/var/www/files',
			'deny':		'/var/www/files/badstuff'
			'allow':	'/var/www/files/badstuff/butthisok',
			'denyfirst': true
			}
		}
  * ACL_FILE - same as ACL but also supports brute force protections

* context menus should appear on entire section not just rows
* fix search ribbon ui
* fix address bar span text 1px too low
* fix address bar span text overflow
* handle response format errors on login
* column widths
* credit minifiers, jquery, normalize, skeleton

* add download/upload abort
* duplicate subdirs
* multiple upload/download
* implement drag/drop copy/move
* add invalid characters by OS JS
* validate upload filenames and size
* validate all renames on front end
* validate full copies and moves on back end
* improve file class for windows
* combine dir/file copies/moves, etc into one function
* fix vertical alignment issues between text input and buttons
* remove ` ` ? (aka old IE compatibility)
* automate testing
