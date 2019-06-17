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
  * fix login form user

* UI Improvements
  * fix search ribbon ui
  * fix address bar span text 1px too low
  * fix address bar span text overflow
  * fix vertical alignment issues between text input and buttons
  * Mobile CSS

* Testing
  * Check Edge, Chrome, Safari
  * Automate

* Additional Features
  * resizable columns
  * add download/upload abort
  * duplicate subdirs
  * multiple upload/download
  * implement drag/drop copy/move

* UX Improvmenets
  * validate upload filenames and size
  * validate full copies and moves on back end

* Backend improvements
  * improve file class for windows
  * combine dir/file copies/moves, etc into one function
  * remove ` ` ? (aka old IE compatibility)
