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
	
* minification

* handle response format errors on login
* column widths
* add download/upload abort
* multiple upload/download
* duplicate subdirs
* add invalid characters by OS JS
* validate upload filenames and size
* validate all renames on front end
* validate full copies and moves on back end
* implement drag/drop copy/move
* implement drag/drop file upload
* improve file class for windows
* combine dir/file copies/moves, etc into one function
* fix vertical alignment issues between text input and buttons
* remove `` ? (aka old IE compatibility)
* automate testing