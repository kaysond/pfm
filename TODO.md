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
	
* add subdirectory->go
* check existing subdirs for new subdir
* improve search UX
* make menu more obvious
* handle response format errors on login
* column widths
* upload complete message should tell which dir
* duplicate subdirs

* add download/upload abort
* upload/download queues
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
