//pfm.js
var dir = {
	path: "",
	sort: "name",
	sortAsc: true,
	poppingHistory: false,
	pendingRequests: 0,
	pattern: "",
	replace: "",
	hotkeys: {
		"ctrl+shift+c": function() {
			if (this.getSelectedSubdirs().length < 1)
				$("#files-form input[value='Copy']").click()
			else if (this.getSelectedFiles().length < 1)
				$("#subdirs-form input[value='Copy']").click()
			else if (dir.lastInteraction == "subdirs")
				$("#subdirs-form input[value='Copy']").click()
			else
				$("#files-form input[value='Copy']").click()
		},
		"delete": function() {
			if (this.getSelectedSubdirs().length < 1)
				$("#files-form input[value='Delete']").click()
			else if (this.getSelectedFiles().length < 1)
				$("#subdirs-form input[value='Delete']").click()
			else if (dir.lastInteraction == "subdirs")
				$("#subdirs-form input[value='Delete']").click()
			else
				$("#files-form input[value='Delete']").click()
		},
		"ctrl+shift+f": function() {
			$("#filter").focus()
		},
		"ctrl+shift+k": function() {
			$("#subdirs-form input[value='Kill']").click()
		},
		"ctrl+shift+n": function() {
			if (this.getSelectedSubdirs().length < 1)
				$("#files-form input[value='New']").click()
			else if (this.getSelectedFiles().length < 1)
				$("#subdirs-form input[value='New']").click()
			else if (dir.lastInteraction == "subdirs")
				$("#subdirs-form input[value='New']").click()
			else
				$("#files-form input[value='New']").click()
		},
		"ctrl+shif+o": function() {
			$("#files-form input[value='Edit']").click()
		},
		"ctrl+shift+s": function() {
			$("li.search").click()
		},
		"ctrl+shift+x": function() {
			if (this.getSelectedSubdirs().length < 1)
				$("#files-form input[value='Move']").click()
			else if (this.getSelectedFiles().length < 1)
				$("#subdirs-form input[value='Move']").click()
			else if (dir.lastInteraction == "subdirs")
				$("#subdirs-form input[value='Move']").click()
			else
				$("#files-form input[value='Move']").click()
		},
		"escape": function() {
			dir.resetUI()
		}
	},
	refresh: function(path) {
		if (!this.baseURLPath) {
			return this.execute("get_config", {}, null, function(data) {
				for (let conf in data) {
					this[conf] = data[conf]
				}
				$("#max-file-size").val(this.maxUploadSize)
			}.bind(this)).done(function() {
				this.execute("refresh", {path: path})
			}.bind(this))
		}
		else {
			return this.execute("refresh", {path: path})
		}
	},
	update: function(data) {
		if (typeof data !== "undefined") { //Allows calls of update() to use existing data
			if (!data.path || !data.subdirs || !data.files) {
				this.error("Could not update directory information from server response")
				return
			}
			this.path = data.path
			this.subdirs = data.subdirs
			this.files = data.files
		}

		if (!this.poppingHistory) {
			if (location.hash == "" || decodeURIComponent(location.hash.substr(1)) == this.path)
				history.replaceState({path: this.path}, `PFM File Manager - ${this.path}`, "#" + encodeURIComponent(this.path))
			else
				history.pushState({path: this.path}, `PFM File Manager - ${this.path}`, "#" + encodeURIComponent(this.path))
		}
		this.poppingHistory = false

		this.sortFiles(this.sort, this.sortAsc)
		$("input.path").val(this.path)
		var pathEles = this.path.split(this.separator)
		var span = $('span.path').empty()
		for (var i = 0; i < pathEles.length - 1; i++) {
			span.append($('<span>').addClass('nav').text(pathEles[i] + ' ' + this.separator))
		}
		span.append($('<span>').addClass('nav').text(pathEles[pathEles.length - 1]))

		$("#subdirs").empty()
		if (this.path != dir.separator)
			$("#subdirs").append($("<tr class='subdir'><td class='subdir'>..</td></tr>"))
		this.subdirs.forEach(function(subdir) {
			$("#subdirs").append($("<tr class='subdir'>").append($("<td class='subdir'>").text(subdir).attr("title", subdir)))
		})

		$("#check-all").prop("checked", false)

		$("#files").empty()
		var file_path = this.path == dir.separator ? "" : this.path
		this.files.forEach(function(file) {
			var checkbox = $("<td class='col-checked'>").append($(`<input class="files" type="checkbox" value="${file.name}">`))
			var name = $("<td class='col-name'>").append($(`<a href="${this.baseURLPath}${file_path}/${file.name}" target="_BLANK" title="${file.name}">${file.name}</a>`))
			var size = $("<td class='col-size'>").text(dir.formatSize(file.size))
			var owner = $("<td class='col-owner'>").text(file.owner)
			var group = $("<td class='col-group'>").text(file.group)
			var perms = $("<td class='col-permissions'>").text(dir.formatPerms(file.permissions))
			var created = $("<td class='col-created'>").text(dir.formatDate(file.created))
			var modified = $("<td class='col-modified'>").text(dir.formatDate(file.modified))
			var row = $("<tr class='file'>").append(checkbox).append(name).append(size).append(owner).append(group).append(perms).append(created).append(modified)
			$("#files").append(row)
		}.bind(this))
		$("th.files").removeClass("sort_asc sort_desc")
		$("th.files:contains('" + this.sort.substr(1) + "')").addClass(dir.sortAsc ? "sort_asc" : "sort_desc")
		this.toggleColumns()
	},
	sortFiles: function(by, asc) {
		switch (by) {
			default:
			case "name":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.name.localeCompare(fileb.name)
					else
						return fileb.name.localeCompare(filea.name)
				})
				break
			case "size":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.size - fileb.size
					else
						return fileb.size - filea.size
				})
				break
			case "owner":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.owner.localeCompare(fileb.owner)
					else
						return fileb.owner.localeCompare(filea.owner)
				})
				break
			case "group":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.group.localeCompare(fileb.group)
					else
						return fileb.group.localeCompare(filea.group)
				})
				break
			case "permissions":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.permissions.localeCompare(fileb.permissions)
					else
						return fileb.permissions.localeCompare(filea.permissions)
				})
				break
			case "created":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.created - fileb.created
					else
						return fileb.created - filea.created
				})
				break
			case "modified":
				this.files.sort(function(filea, fileb) {
					if (asc)
						return filea.modified - fileb.modified
					else
						return fileb.modified - filea.modified
				})
				break
		}
	},
	toggleColumns: function() {
		$("#filecolumns-menu input").each(function() {
			$(".col-" + this.id.substr(6)).toggleClass("hidden", !this.checked)
		})
		this.saveSettings()
	},
	filter: function(filter) {
		$("tr.file, tr.subdir").hide()
		$("td.col-name").filter(function() { return $(this).find("a").text().includes(filter.toLowerCase()) }).parent().show()
		$("td.subdir").filter(function() { return $(this).text().toLowerCase().includes(filter.toLowerCase()) }).parent().show()
		$("td.subdir:contains('..')").parent().show()
	},
	isFile: function(name) {
		return this.files.map(function(file) {return file.name}).includes(name)
	},
	isDir: function(name) {
		return this.subdirs.includes(name)
	},
	isValidName: function(name) {
		for (var i = 0; i < this.invalidChars.length; i++) {
			if (name.indexOf(this.invalidChars[i]) != -1)
				return false
		}
		if (name == "" || name == ".." || name == ".")
			return false
		return true
	},
	makeDir: function(name) {
		if (name == null)
			return
		else if (!this.isValidName(name)) {
			this.error(`Invalid directory name: ${name}`)
			return
		}
		else if (this.isDir(name)) {
			this.error(`Directory "${name}" already exists`)
			return
		}
		return this.execute("make_dir", {"name": name}, "Created directory " + name)
	},
	removeDirs: function(names) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.subdirs.includes(names[i])) {
				this.error(`Directory "${names[i]}" does not exist`)
				return
			}
		}
		
		plural = names.length > 1 ? "ies" : "y"
		return this.execute("remove_dirs", {"names[]": names}, `Removed ${names.length} director${plural}`)
	},
	renameDir: function(from, to) {
		if (to == null)
			return

		if (!this.isDir(from)) {
			this.error(`Directory "${from}" does not exist`)
			return
		}

		if (!this.isValidName(to)) {
			this.error(`Invalid directory name: ${to}`)
			return
		}

		if (this.isDir(to)) {
			this.error(`Directory "${to}" already exists`)
		}

		return this.execute("rename", {from: from, to: to}, `Renamed ${from} to ${to}`)
	},
	copyDirs: function(names, to) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isDir(names[i])) {
				this.error(`Subdirectory "${names[i]}" does not exist`)
				return
			}
		}

		plural = names.length > 1 ? "ies" : "y"
		return this.execute("copy", {"names[]": names, to: to}, `Copied ${names.length} director${plural} to ${to}`)
	},
	moveDirs: function(names, to) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isDir(names[i])) {
				this.error(`Subdirectory "${names[i]}" does not exist`)
				return
			}
		}

		plural = names.length > 1 ? "ies" : "y"
		return this.execute("move", {"names[]": names, to: to}, `Moved ${names.length} director${plural} to ${to}`)
	},
	killDirs: function(names) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isDir(names[i])) {
				this.error(`Directory "${names[i]}" does not exist`)
				return
			}
		}

		plural = names.length > 1 ? "ies" : "y"
		return this.execute("kill_dirs", {"names[]": names}, `Killed ${names.length} director${plural}`)
	},
	getSelectedSubdirs: function() {
		return $("tr.subdir.selected:not(:contains('..'))").map(function() {
			return $(this).text()
		}).toArray()
	},
	duplicate: function(from, to) {
		if (to == null)
			return

		if (!this.isFile(from)) {
			this.error(`File "${from}" does not exist`)
			return
		}

		if (!this.isValidName(to)) {
			this.error(`Invalid name: ${to}`)
			return
		}

		if (this.isFile(to)) {
			this.error(`File "${to}" already exists`)
			return
		}

		return this.execute("duplicate", {from: from, to: to}, `Duplicated ${from}`)
	},
	regexRenameTest: function(pattern, replace) {
		return this.execute("regex_rename_test", {pattern: pattern, replace: replace}, null, function(data) {
				this.pattern = pattern
				this.replace = replace
				this.renameCount = data.renames.length
				this.showRegexRenames(data.renames)
		}.bind(this))
	},
	regexRename: function() {
		return this.execute("regex_rename", {pattern: this.pattern, replace: this.replace}, `Renamed ${this.renameCount} file` + (this.renameCount > 1 ? "s" : "")).done(function() {
			this.pattern = ""
			this.replace = ""
			this.renameCount = 0
		}.bind(this))
	},
	delete: function(names) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isFile(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}

		return this.execute("delete", {"names[]": names}, `Deleted ${names.length} file` + (names.length > 1 ? "s" : ""))
	},
	moveFiles: function(names, to) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isFile(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}

		return this.execute("move", {"names[]": names, to: to}, `Moved ${names.length} file` + (names.length > 1 ? "s" : "") + ` to ${to}`)
	},
	copyFiles: function(names, to) {
		if (names.length < 1)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.isFile(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}

		return this.execute("copy", {"names[]": names, to: to}, `Copied ${names.length} file` + (names.length > 1 ? "s" : "") + ` to ${to}`)
	},
	renameFile: function(from, to) {
		if (to == null)
			return

		if (!this.isFile(from)) {
			this.error(`File "${from}" does not exist`)
			return
		}

		if (!this.isValidName(to)) {
			this.error(`Invalid file name: ${to}`)
			return
		}

		if (this.isFile(to)) {
			this.error(`File "${to}" already exists`)
			return
		}

		return this.execute("rename", {from: from, to: to}, `Renamed ${from} to ${to}`)
	},
	newFile: function(name) {
		if (!this.isValidName(name)) {
			this.error(`Invalid file name: ${name}`)
			return
		}

		if (this.isFile(name)) {
			this.error(`File "${name}" already exists`)
			return
		}

		return this.execute("new_file", {name: name}, `Created file ${name}`)
	},
	editFile: function(name) {
		if (!this.isFile(name)) {
			this.error(`Could not find file "${file}"`)
			return
		}
		return this.execute("get_contents", {name: name}, null, function(data) {
			this.openFile = name
			$("#edit").val(data.contents)
			$("#edit-box").show()
		}.bind(this))
	},
	saveFile: function() {
		return this.execute("put_contents", {name: this.openFile, contents: $("#edit").val()}, `Saved ${this.openFile}`)
	},
	search: function(query, depth, regex) {
		return this.execute("search", {query: query, depth: depth, regex: regex}, null, function(data) {
			if (data.results.length < 1) {
				this.toast("No results found for " + query)
				return
			}
			$("#search-results").empty()
			data.results.forEach(function(result) {
				var a = $("<a href='" + this.baseURLPath + result + "' target='_BLANK' title='" + this.baseURLPath + result + "'>" + result + "</a>")
				var pieces = result.split(dir.separator)
				var path = pieces.slice(0, -1).join(dir.separator)
				var file = pieces.reverse()[0]
				var onclick = "window.dir.refresh('" + path + "').done(function() { window.dir.selectFile('" + file + "'); window.dir.resetUI()})" 
				var a2 = $("<a onclick=\"" + onclick + "\" class='jumpto'>Jump to</a>")
				$("#search-results").append($("<tr>").append($("<td>").append(a)).append($("<td>").append(a2)))
			}.bind(this))
			$("div.modal.search-results").show()
			$("#search-results-list").scrollTop(0)
		}.bind(this))
	},
	download: function(names) {
		if (this.lastInteraction == "files") {
			var totalSize = 0
			names.forEach(function(name) {
				totalSize += dir.files.filter(function(file) { return file.name == name })[0].size
			})
			if (names.length > 1 && totalSize > 512*1024*1024) {
				if (!confirm("Total combined file size is larger than 500 MiB and may take a long time to zip. Continue anyway?"))
					return
			}
			if (totalSize > 1024*1024*1024) { //dont show progress for downloads larger than 1 GiB due to buffer limitations
				var query = "?download&path=" + encodeURIComponent(this.path)
				names.forEach(function(name) {
					query += "&names[]=" + encodeURIComponent(name)
				})
				window.location = query
			}
			else {
				this.download_wprogress(names)
			}
		}
		else {
			var subdirs = this.getSelectedSubdirs()
			var size = 0
			Promise.all(subdirs.map(function(subdir) {
				return this.execute("size", {name: subdir}, false, function(data) { if (data.size) {size += data.size} })
			}.bind(this))).then(function() {
				if (size > 512*1024*1024) {
					if (!confirm("Total combined file size is larger than 500 MiB and may take a long time to zip. Continue anyway?"))
						return
				}
				if (totalSize > 1024*1024*1024) { //1GiB
					var query = "?download&path=" + encodeURIComponent(this.path)
					names.forEach(function(name) {
						query += "&names[]=" + encodeURIComponent(name)
					})
					window.location = query
				}
				else {
					this.download_wprogress(names)
				}
			}.bind(this))
		}
	},
	download_wprogress: function(names) {
		if ($("#dl-progress:visible").length) { //this is a weak check and should be fixed
			this.error("Download already in progress")
			return false
		}
		$("#dl-progress").show()
		if (names.length > 1 || this.lastInteraction == "subdirs")
			filename = "files.zip"
		else
			filename = names[0]
		$.ajax({
			url: "?download",
			method: "POST",
			data: {path: this.path, names: names},
			dataType: false,
			xhr: function() {
				var myXhr = $.ajaxSettings.xhr();
				myXhr.responseType = "arraybuffer"
				myXhr.addEventListener("progress", function(e) {
					if (e.lengthComputable) {
						var percent = Math.round(e.loaded / e.total * 100);
						var text = dir.formatSize(e.loaded, true) + "/" + dir.formatSize(e.total, true)
						$("#dl-progress-bar").css("width", percent + "%");
						$("#dl-progress-text").text(text)
					}
				})
				return myXhr;
			},
			success: function(data) {
				$("#dl-progress").hide()
				$("#dl-progress-bar").css("width", "0")
				$("#dl-progress-text").html("&nbsp;")
			    var bufView = new Uint8Array(data);

			    //If the file starts with {" and is sufficiently short, it might be a pfm error
			    if (bufView[0] == 123 && bufView[1] == 34 && bufView.length < 200) {
			    	var string = decodeURIComponent(escape(String.fromCharCode.apply(null, Array.prototype.slice.apply(bufView))))
			    	try {
			    		var json = JSON.parse(string)
			    		if (json.success === false && json.error) {
			    			dir.error(json.error)
			    			return
			    		}
			    	}
			    	catch (e) {}
			    }

				if (data != null && navigator.msSaveBlob)
					return navigator.msSaveBlob(new Blob([data], { type: type }), name)
				var a = $("<a style='display: none;'/>")
				var blob = new Blob([data], {type: "application/octet-stream"})
				var url = window.URL.createObjectURL(blob)
				a.attr("href", url)
				a.attr("download", filename)
				$("body").append(a)
				a[0].click()
				setTimeout(function() {
					window.URL.revokeObjectURL(url);
					a.remove()
				}, 0)
			},
			error: function(req, status, error) {
				$("#dl-progress").hide()
				$("#dl-progress-bar").css("width", "0")
				$("#dl-progress-text").html("&nbsp;")
				dir.ajaxError(req, status, error)
			}
		})
		//Dummy request to get a fresh session ID before the old one expires in case of long downloads
		this.execute("is_logged_in", {}, null, function() {})
	},
	upload: function() {
		var numFiles = $("#upload")[0].files.length
		var data = new FormData($("#upload-form")[0])
		data.set("path", this.path)
		this.uploadPath = this.path
		$("#ul-progress").show()
		$.ajax({
			url: "?upload",
			method: "POST",
			data: data,
			contentType: false,
			processData: false,
			xhr: function() {
				var myXhr = $.ajaxSettings.xhr();
				if (myXhr.upload) {
					myXhr.upload.addEventListener("progress", function(e) {
						if (e.lengthComputable) {
							var percent = Math.round(e.loaded / e.total * 100);
							var text = dir.formatSize(e.loaded, true) + "/" + dir.formatSize(e.total, true)
							$("#ul-progress-bar").css("width", percent + "%");
							$("#ul-progress-text").text(text)
						}
					})
				}
				return myXhr;
			},
			success: function(data) {
				$("#ul-progress").hide()
				$("#ul-progress-bar").css("width", "0")
				$("#ul-progress-text").html("&nbsp;")
				if (typeof data == 'undefined' || data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					//If something was partially successful, update anyway
					if (data.path && data.subdirs && data.files && this.path == this.uploadPath) {
						this.update(data)
					}
				}
				else {
					if (this.path == this.uploadPath)
						this.update(data)
					var plural = numFiles > 1 ? "s" : ""
					this.toast("Uploaded " + numFiles + " file" + plural + " to " + this.uploadPath)
				}
				this.uploadPath = ''
			}.bind(this),
			error: function(req, status, error) {
				$("#ul-progress").hide()
				$("#ul-progress-bar").css("width", "0")
				$("#ul-progress-text").html("&nbsp;")
				this.uploadPath = ''
				this.ajaxError(req, status, error)
			}.bind(this),
		})
		//Dummy request to get a fresh session ID before the old one expires in case of long uploads
		this.execute("is_logged_in", {}, null, function() {})
	},
	toast: function(toast) {
		var text
		if ($("#toast:visible").length)
			text = toast + "\n" + $("#toast").text()
		else
			text = toast
		$("#toast").text(text).fadeIn()
		clearTimeout(this.toastTimeout)
		this.toastTimeout = setTimeout(function() { $("#toast").fadeOut() }, 3000)
	},
	error: function(error) {
		var text
		if ($("#error:visible").length)
			text = error + "\n" + $("#error").text()
		else
			text = error
		$("#error").text(text).fadeIn()
		clearTimeout(this.errorTimeout)
		this.errorTimeout = setTimeout(function() { $("#error").fadeOut() }, 3000)
		if (error == "Not logged in")
			$("#logout").click()
	},
	showContextMenu: function(event) {
		dir.resetUI()
		if (this.lastInteraction == "files") {			
			$("#files-menu").hide().removeClass("ribbon").addClass("contextmenu")
			var top = 0
			if (event.pageY + $('#files-menu').height() > $(window).height() - 20)
				$("#files-menu").css({'bottom': $(window).height() - event.pageY,'left': event.pageX, 'top': ''}).show()	
			else
				$("#files-menu").css({'top': event.pageY, 'left': event.pageX, 'bottom': ''}).show()
		}
		else {
			$("#subdirs-menu").hide().removeClass("ribbon").addClass("contextmenu")
			var top = 0
			if (event.pageY + $('#subdirs-menu').height() > $(window).height() - 20)
				$("#subdirs-menu").css({'bottom': $(window).height() - event.pageY,'left': event.pageX, 'top': ''}).show()	
			else
				$("#subdirs-menu").css({'top': event.pageY, 'left': event.pageX, 'bottom': ''}).show()
		}
	},
	clearErrors: function() {
		$("#error").fadeOut()
	},
	startIndicator: function() {
		if (this.pendingRequests == 0)
			this.indicatorInterval = setInterval(this.indicator, 1000)

		this.pendingRequests++
	},
	clearIndicator: function() {
		this.pendingRequests--
		if (this.pendingRequests == 0) {
			clearInterval(this.indicatorInterval)
			$("div.title").text("PFM File Manager")
		}
	},
	indicator: function() {
		var text = $("div.title").text()
		if (text == "...PFM File Manager...")
			$("div.title").text("PFM File Manager")
		else
			$("div.title").text("." + text + ".")
	},
	clear: function() {
		$("input.path").val("")
		$("#subdirs").empty()
		$("#files").empty()
		$("#search-results").empty()
		$("#dir-select").empty()

		$("#pattern").val("")
		$("#replace").val("")
		$("#edit").val("")

		$("#error").text("")
		$("#toast").text("")

		this.clearIndicator()
	},
	formatSize: function(size, fixed = false) {
		var i = size == 0 ? 0 : Math.floor( Math.log(size) / Math.log(1024));
		var num = fixed ? (size / Math.pow(1024, i)).toFixed(2) : (size / Math.pow(1024, i)).toFixed(2) * 1
		return num + ' ' + ['B', 'KiB', 'MiB', 'GiB', 'TiB'][i];
	},
	formatPerms: function(perms) {
		return perms
	},
	formatDate: function(timestamp) {
		return new Date(timestamp*1000).toISOString().substr(0, 19).replace('T', ' ');
	},
	getSelectedFiles: function() {
		return $("input.files:checked").map(function() { return $(this).val() }).toArray()
	},
	selectFile: function(file) {
		var row = $("td.col-name a").filter(function() { return $(this).text() === file }).parents("tr")
		row.addClass("selected")
		row.find("input[type='checkbox']").prop("checked", true)
	},
	showRegexRenames: function(renames) {
		$("#regex-rename-form input[type!='text']").hide()
		$("#regex-rename-form input.confirm").show()
		$("#pattern").prop("disabled", true).prop("autocomplete", "off")
		$("#replace").prop("disabled", true).prop("autocomplete", "off")
		renames.forEach(function(rename) {
			$(`td.col-name`).filter(function() { 
				return $(this).text() === rename.from
			}).append($(`<span><br />&emsp;&#x27A5; ${rename.to}</span>`))
		})
	},
	clearRegexRenames: function() {
		$("td.col-name > span").remove()
		$("#regex-rename-form input[type!='text']").hide()
		$("#regex-rename-form input.rename").show()
		$("#pattern").prop("disabled", false).prop("autocomplete", "on")
		$("#replace").prop("disabled", false).prop("autocomplete", "on")
		this.pattern = ""
		this.replace = ""
	},
	populateDirSelect: function() {
		var title = ""
		if (dir.dirSelectType == "file") {
			if ($("#files-action").val() == "copy")
				title = "Select destination for file copy"
			else if ($("#files-action").val() == "move")
				title = "Select destination for file move"
		}
		else if (dir.dirSelectType == "dir") {
			if ($("#subdirs-action").val() == "copy")
				title = "Select destination for subdirectory copy"
			else if ($("#subdirs-action").val() == "move")
				title = "Select destination for subdirectory move"
		}
		$("div.modal.dir-select th").text(title)
		$("#dir-select").empty()
		$("#dir-select").append($("<tr>").append($("<td>").css("text-indent", "0rem").attr("title", dir.separator).text(dir.separator).append($("<input type='hidden' value='" + dir.separator + "'>"))))
		this.selectDir($("#dir-select tr"))
		$("div.modal.dir-select").show()
	},
	selectDir: function(ele) {
		$("#dir-select .selected").removeClass("selected")
		ele.addClass("selected")
		if (!ele.hasClass("expanded")) {
			ele.addClass("expanded")
			var path = ele.find("input").val()
			return this.execute("get_subdirs", {"path": path}, null, function(data) {
				if (data.path != path) {
					this.error("Received server response for different path than requested")
					return
				}
				if (data.subdirs.length > 0) {
					if (path == dir.separator)
						path = ""
					var indent = path.split(dir.separator).length * 3
					data.subdirs.reverse().forEach(function(subdir) {
						ele.after($("<tr>").append($("<td>").css("text-indent", indent + "rem").text(subdir).attr("title", subdir).append("<input type='hidden' value='" + path + dir.separator + subdir + "'>")))
					})
				}
			})
		}
	},
	getSelectedDir: function() {
		return $("#dir-select tr.selected input").val()
	},
	resetUI: function() {
		this.clearRegexRenames()
		$("div.toast").hide()
		$("div.ribbon").hide()
		$("ul.ribbon li.selected").removeClass("selected")
		$("div.modal").hide()
		$(".contextmenu").hide()
		$("#filter").val('')
		this.filter('')
		$("#filter").blur()
	},
	loadSettings: function() {
		if (localStorage) {
			$(".setting").each(function() {
				if (this.id.length > 0) {
					var storage = localStorage.getItem(this.id)
					if (storage !== null) {
						switch (storage) {
							case "true":
							case "false":
								this.checked = (storage == "true")
								break
							default:
								$(this).val(storage)
						}
					}
				}
			})
		}
	},
	saveSettings: function() {
		if (localStorage) {
			$(".setting").each(function() {
				if (this.id.length > 0) {
					var value
					if ($(this).attr('type') == 'checkbox')
						value = $(this).prop('checked')
					else
						value = $(this).val()
					localStorage.setItem(this.id, value)
				}
			})
		}
	},
	hotkey: function(e) {
		var keyCombo = ""
		if (window.ctrl)
			keyCombo += "ctrl+"
		if (window.shift)
			keyCombo += "shift+"
		if (window.alt)
			keyCombo += "alt+"

		var keyCode = e.which
		switch (keyCode) {
			case 8:
				keyCombo += "backspace"
				break
			case 9:
				keyCombo += "tab"
				break
			case 13:
				keyCombo += "enter"
				break
			case 27:
				keyCombo += "escape"
				break
			case 32:
				keyCombo += "space"
				break
			case 46:
				keyCombo += "delete"
				break
			default:
				if (keyCode < 91 && keyCode > 64) { //letters
					var alphabet = "abcdefghijklmnopqrstuvwxyz"
					keyCombo += alphabet[keyCode-65]
				}
		}

		if (this.hotkeys[keyCombo]) {
			this.hotkeys[keyCombo].call(this)
			e.preventDefault()
		}
	},
	execute: function(command, data, toast, update) {
		this.startIndicator()
		if (typeof data.path == "undefined")
			data.path = this.path
		return $.post({
			url: "?" + command,
			data: data,
			dataType: "json",
			success: function(data) {
				if (typeof data == 'undefined' || data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					//If something was partially successful, update anyway
					if (data.path && data.subdirs && data.files) {
						this.update(data)
					}
				}
				else {
					if (!update)
						this.update(data)
					else
						update(data)

					if (toast)
						this.toast(toast)
				}
			}.bind(this),
			error: dir.ajaxError.bind(this),
			complete: function() {
				this.clearIndicator()
			}.bind(this)
		})
	},
	ajaxError: function(req, status, error) {
		switch (status) {
			case "timeout":
				this.error("Network connection timed out")
				break
			case "abort":
				this.error("Network connection aborted")
				break
			case "error":
				this.error("Network esponse error: " + error)
				break
			case "parsererror":
				this.error("Received malformed network response")
				break
			default:
				this.error("Unknown network error")
		}
	}
}

$(function() {
	$("#header").hide()
	$("#subheader").hide()
	$("#manager").hide()
	$.post({
		url: "?is_logged_in",
		dataType: "json",
		success: function(data) {
			if (data.success) {
				dir.loadSettings()
				if (history.state !== null) {
					dir.refresh(history.state.path)
				}
				else if (location.hash) {
					dir.refresh(decodeURIComponent(location.hash.substr(1)))
				}
				else {
					dir.refresh("")
				}
				$("#header").show()
				$("#subheader").show()
				$("#manager").show()
			}
			else {
				$("#login").show()
				$("#password").focus()
			}
		}
	})

	$("#login-form").submit(function(e) {
		$.post({
			url: "?login",
			data: {"username":"test","password":$("#password").val()},
			dataType: "json",
			success: function(data) {
				if (data.error || !data.success) {
					dir.error(data.error)
				}
				else {
					$("#login-errors").text("").hide()
					$("#login").hide()
					dir.loadSettings()
					dir.refresh("").done(function() {
						$("#header").show()
						$("#subheader").show()
						$("#manager").show()
					})
				}
			},
			error: dir.ajaxError.bind(dir)
		})
		$("#password").val("")
		e.preventDefault()
	})

	$("#logout").click(function(e) {
		$.post({
			url: "?logout",
			dataType: "json",
			success: function(data) {
				if (data.success) {
					dir.resetUI()
					dir.clear()
					$("#header").hide()
					$("#subheader").hide()
					$("#manager").hide()
					$("#login").show()
				}
				else {
					dir.error("An unknown error occurred while attempting to log out")
				}
			}
		})
		e.preventDefault()
	})

	$("#subdirs-form > input[type='submit']").click(function(e) {
		$("#subdirs-action").val(this.value.toLowerCase())
	})
	$("#subdirs-form").submit(function(e) {
		dir.resetUI()		
		switch ($("#subdirs-action").val()) {
			case "go":
				var subdirs = dir.getSelectedSubdirs()
				if (subdirs.length == 1) {
					dir.resetUI()
					dir.refresh(dir.path + dir.separator + subdirs[0])
				}
				break
			case "up":
				dir.refresh($("input.path").val().split(dir.separator).slice(0,-1).join(dir.separator))
				break
			case "new":
				var name = prompt("Enter new directory name:")
				if (name !== null)
					dir.makeDir(name)
				break
			case "delete":
				var selectedSubdirs = dir.getSelectedSubdirs()
				var l = selectedSubdirs.length
				var plural = l > 1 ? "ies" : "y"
				if (selectedSubdirs.length > 0 && confirm(`Do you really want to delete ${l} director${plural}? This cannot be undone`))
					dir.removeDirs(selectedSubdirs)
				break
			case "rename":
				var selectedSubdirs = dir.getSelectedSubdirs()
				if (selectedSubdirs.length == 0) {
					dir.error("No directory selected for rename")
				}
				else if (selectedSubdirs.length > 1) {
					dir.error("Only one directory may be renamed at a a time")
				}
				else {
					dir.renameDir(selectedSubdirs[0], prompt("Enter new directory name:"))
				}
				break
			case "move":
			case "copy":
				if (dir.getSelectedSubdirs().length < 1) {
					dir.error("No subdirectories selected")
				}
				else {
					dir.dirSelectType = "dir"
					dir.populateDirSelect()
				}
				break
			case "kill":
				var selectedSubdirs = dir.getSelectedSubdirs()
				var l = selectedSubdirs.length
				var plural = l > 1 ? "ies" : "y"
				if (selectedSubdirs.length > 0 && confirm(`Do you really want to kill ${l} director${plural}? This cannot be undone`))
					dir.killDirs(selectedSubdirs)
				break
			case "download":
				var subdirs = dir.getSelectedSubdirs()
				if (subdirs.length < 1)
					dir.error("No subdirectories selected for download")
				else
					dir.download(dir.getSelectedSubdirs())
				break
			case "select all":
				if ($("tr.subdir").length - $("tr.subdir.selected").length <= 1)
					$("tr.subdir").removeClass("selected")
				else
					$("tr.subdir").addClass("selected")
					$("td.subdir:contains('..')").parent().removeClass("selected")
				break
		}
		e.preventDefault()
	})

	$("#files-form > input[type='submit']").click(function(e) {
		$("#files-action").val(this.value.toLowerCase())
	})
	$("#files-form").submit(function(e) {
		dir.resetUI()
		switch ($("#files-action").val()) {
			case "delete":
				var files = dir.getSelectedFiles()
				if (files.length < 1)
					dir.error("No files selected for deletion")
				else if (confirm("Do you really want to delete " + files.length + " files? This cannot be undone"))
					dir.delete(files)
				break
			case "rename":
				var files = dir.getSelectedFiles()
				if (files.length < 1) {
					dir.error("No file selected for rename")
				}
				else if (files.length != 1) {
					dir.error("Only one file may be renamed at a time")
				}
				else {
					dir.renameFile(files[0], prompt("Enter new file name:"))
				}
				break
			case "duplicate":
				var files = dir.getSelectedFiles()
				if (files.length < 1) {
					dir.error("No file selected for duplication")
				}
				else if (files.length != 1) {
					dir.error("Only one file may be duplicated at a time")
				}
				else {
					dir.duplicate(files[0], prompt("Enter duplicate file name:"))
				}
				break
			case "regex rename":
				$("div.ribbon.regex-rename").show()
				$("#pattern").focus()
				break
			case "kill":
				var l = $("#subdirs").val().length
				var plural = l > 1 ? "ies" : "y"
				if (l > 0 && confirm("Do you really want to kill " + l + " director" + plural + "? This cannot be undone."))
					dir.killDirs($("#subdirs").val())
				break
			case "new":
				var name = prompt("Enter new file name:")
				if (name !== null)
					dir.newFile(name)
				break
			case "edit":
				var checkedFiles = dir.getSelectedFiles()
				if (checkedFiles.length > 1) {
					dir.error("Only one file may be edited at a time")
				}
				else if (!dir.openFile || dir.openFile == "") {
					dir.editFile(checkedFiles[0])
				}
				break
			case "download":
				var files = dir.getSelectedFiles()
				if (files.length < 1)
					dir.error("No files selected for download")
				else
					dir.download(files)
				break
			case "upload":
				$("div.ribbon.upload").show()
				break
			case "move":
			case "copy":
				if (dir.getSelectedFiles().length < 1) {
					dir.error("No files selected")
				}
				else {
					dir.dirSelectType = "file"
					dir.populateDirSelect()
				}
		}
		e.preventDefault();
	})

	$("#edit-form > input[type='submit']").click(function(e) {
		$("#edit-action").val(this.value.toLowerCase())
	})
	$("#edit-form").submit(function(e) {
		dir.resetUI()
		switch ($("#edit-action").val()) {
			case "save":
				dir.saveFile()
				break
			case "save & close":
				dir.saveFile().done(function() {
					dir.openFile = ""
					$("#edit").val("")
					$("#edit-box").hide()
				})				
				break
			case "close":
				dir.openFile = ""
				$("#edit").val("")
				$("#edit-box").hide()
				break			
		}
		e.preventDefault();
	})
	//Insert \t instead of switching form elements
	$("#edit").keydown(function(e) {
		if (e.which == 9) {
			e.preventDefault();
			var start = this.selectionStart;
			var end = this.selectionEnd;
			var text = $(this).val().substring(0, start) + "\t" + $(this).val().substring(end)
			$(this).val(text);
			this.selectionStart = this.selectionEnd = start + 1;
		}
	})

	$("#filter").keyup(function() {
		dir.filter($("#filter").val())
	})

	$("#search-form").submit(function(e) {
		dir.saveSettings()
		if ($("#search-query").val() != "") {
			dir.resetUI()
			dir.search($("#search-query").val(), $("#search-depth").val(), $("#search-regex").prop("checked"))
		}
		e.preventDefault()
	})
	$("#search-results-close").click(function() {
		$("div.search-results").hide()
	})

	$("#regex-rename-form").submit(function(e) {
		if (!dir.pattern && !dir.replace && $("#pattern").val()) {
			dir.regexRenameTest($("#pattern").val(), $("#replace").val())
		}
		else {
			dir.regexRename()
			dir.resetUI()
		}
	})

	$("#regex-rename-clear").click(function(e) {
		dir.clearRegexRenames()
	})

	$("thead.files").contextmenu(function(e) {
		dir.resetUI()
		$("#filecolumns-menu").css({'top':e.pageY,'left':e.pageX}).show()
		return false
	})

	$("#error").click(function(e) {
		$("#error").hide()
	})

	$("#toast").click(function(e) {
		$("#toast").hide()
	})

	$("th.files").click(function(e) {
		var sortby = $(e.target).text().toLowerCase().trim()
		if (dir.sort == sortby)
			dir.sortAsc = !dir.sortAsc
		else
			dir.sortAsc = true
		dir.sort = sortby
		dir.update()
	})

	$("#dir-select-select").click(function(e) {
		if (dir.dirSelectType == "file") {
			if ($("#files-action").val() == "copy")
				dir.copyFiles(dir.getSelectedFiles(), dir.getSelectedDir())
			else if ($("#files-action").val() == "move")
				dir.moveFiles(dir.getSelectedFiles(), dir.getSelectedDir())
		}
		else if (dir.dirSelectType == "dir") {
			if ($("#subdirs-action").val() == "copy")
				dir.copyDirs(dir.getSelectedSubdirs(), dir.getSelectedDir())
			else if ($("#subdirs-action").val() == "move")
				dir.moveDirs(dir.getSelectedSubdirs(), dir.getSelectedDir())
		}
		$("div.modal.dir-select").hide()
	})
	$("#dir-select-cancel").click(function(e) {
		$("div.modal.dir-select").hide()
	})

	$(".contextmenu").contextmenu(function(e) {
		e.preventDefault()
	})

	$(".column-sel").change(function() {
		dir.toggleColumns()
	})

	$("body").keyup(function(e) {
		switch(e.which) {
			case 16:
				window.shift = false
				break
			case 17:
				window.ctrl = false
				break
			case 18:
				window.alt = false
				break
		}
	})
	$("body").keydown(function(e) {
		switch(e.which) {
			case 16:
				window.shift = true
				break
			case 17:
				window.ctrl = true
				break
			case 18:
				window.alt = true
				break
			default:
				dir.hotkey(e)
		}
	})

	$("body").on("click contextmenu", function(e) {
		if ($("div.toast:visible").length) {
			$("div.toast").hide()
			return
		}
		var selectors = [
			"div.ribbon",
			".contextmenu",
			"#dir-select",
		]
		selectors.forEach(function(selector) {
			if ($(selector + ":visible").length && !$(selector).has(e.target).length && !$(selector).is(e.target)) {
				dir.resetUI()
			}
		})
	})
	//Don't resetUI if the click submits a form
	$("form").submit(function(e) {
		e.stopPropagation()
	})

	$("#check-all").change(function(e) {
		if($("#check-all").prop("checked")) {
			$("#files tr input[type='checkbox']").prop("checked", true)
			$("#files tr").addClass("selected")
		}
		else {
			$("#files tr input[type='checkbox']").prop("checked", false)
			$("#files tr").removeClass("selected")				
		}
	})

	$("#files").on("click keyup", "input", function(e) {
		if (e.type == "click" || e.which == 32) //space
			$(this).parents("tr").toggleClass("selected", this.checked)
	})
	$("#files").on("click", "tr", function(e) {
		if (!$(e.target).is("a, input"))
			$(this).find("input").first().click()
	})
	$("div.files").on("contextmenu", function(e) {
		$("tr.file.selected input").prop("checked", false)
		$("tr.file.selected").removeClass("selected")
		dir.lastInteraction = "files"
		dir.showContextMenu(e)
		return false
	})
	$("#files").on("contextmenu", "tr", function(e) {
		dir.lastInteraction = "files"
		if (!$(this).hasClass("selected")) {
			$("tr.file.selected input").prop("checked", false)
			$("tr.file.selected").removeClass("selected")
			$(this).click()
		}
		dir.showContextMenu(e)
		return false
	})
	$("#files").on("click keyup", function() {
		dir.lastInteraction = "files"
	})

	$("#subdirs").on("click", "tr", function() {
		if ($(this).text() != "..")
			$(this).toggleClass("selected")
	})
	$("div.subdirs").on("contextmenu", function(e) {
		dir.lastInteraction = "subdirs"
		$("tr.subdir.selected").removeClass("selected")
		dir.showContextMenu(e)
		return false //stop propagation and default
	})
	$("#subdirs").on("contextmenu", "tr", function(e) {
		dir.lastInteraction = "subdirs"
		if (!$(this).hasClass("selected")) {
			$("tr.subdir.selected").removeClass("selected")
			$(this).addClass("selected")
		}
		dir.showContextMenu(e)
		return false //stop propagation and default
	})
	$("#subdirs").on("dblclick", "tr", function(e) {
		dir.refresh(dir.path + dir.separator + $(this).text())
		e.preventDefault()
	})
	$("#subdirs").on("click", function(e) {
		dir.lastInteraction = "subdirs"
	})

	//Prevent selection on dblclick
	$("#subdirs").mousedown(function(e) {
		e.preventDefault()
	})

	$("#dir-select").on("click", "tr", function(e) {
		dir.selectDir($(this))
	})

	$("ul.ribbon").on("click", "li", function(e) {
		if (this.className.includes("selected")) {
			$("ul.ribbon li.selected").removeClass("selected")
			$("div.ribbon").hide()
		}
		else {
			$("div.ribbon").hide()
			$(".contextmenu").hide()
			var id = "#" + this.className + "-menu"
			$(id).removeClass("contextmenu").css({top: "", left: ""}).addClass("ribbon").show()
			$(id + " input").first().focus()
			$("ul.ribbon li.selected").removeClass("selected")
			$(this).addClass("selected")
		}
		//Don't trip the <body> UI resetter
		e.stopPropagation()
		//But hide toasts
		$(".toast").hide()
	})

	$("#upload-form").submit(function(e) {
		var totalsize = 0
		for (let file of $("#upload")[0].files) {
			if (dir.isFile(file.name)) {
				dir.error("File " + file.name + "already exists")
				e.preventDefault()
				return
			}
			totalsize += file.size
		}
		if (dir.uploadPath != '') {
			dir.error("Upload already in progress")
		}
		else if (totalsize > dir.maxUploadSize) {
			dir.error("Selected files exceed maximum upload size limit of " + dir.formatSize(dir.maxUploadSize))
		}
		else if ($("#upload")[0].files.length > dir.maxUploadCount) {
			var plural = dir.maxUploadCount > 1 ? "s" : ""
			dir.error("Server will not accept more than " + dir.maxUploadCount + " file" + plural + " per upload")
		}
		else if ($("#upload")[0].files.length == 0) {
			dir.error("No files selected for upload")
		}
		else {
			$("div.ribbon.upload").hide()
			$("div.ribbon.upload-progress").show()
			dir.upload()
			$("#upload").val("")
		}
		e.preventDefault()
	})

	$('#addressbar').click(function(e) {
		var target = $(e.target)
		if (target.is($('span.path'))) {
			$('span.path').hide()
			$('input.path').show().focus().select()
			e.stopPropagation()
		}
		else if (target.parent().is($('span.path'))) {
			dir.resetUI()
			dir.refresh(dir.path.split(dir.separator).slice(0, target.index() + 1).join(dir.separator))
		}
	})

	$('#go').mousedown(function(e) {
		e.preventDefault() //prevent input.path blur event
	})

	$('#go').click(function(e) {
		dir.resetUI()
		dir.refresh($('input.path').val()).then(() => {
			$('input.path').blur()
		})
	})
	
	$('#up').click(function(e) {
		$('#subdirs-action').val('up')
		$('#subdirs-form').submit()
	})

	$('input.path').blur(function(e) {
		$('input.path').val(dir.path).hide()
		$('span.path').show()
	})

	$("input.path").keydown(function(e) {
		if (e.which == 13) { //enter
			dir.resetUI()
			dir.refresh($("input.path").val()).then(() => {
				$('input.path').blur()
			})
		}
		if (e.which == 27) { //escape
			$('input.path').blur()
		}
	})

	$('html').on('dragover', function(e) {
		e.stopPropagation();
		e.preventDefault();
	})

	$('html').on('drop', function(e) {
		e.stopPropagation();
		e.preventDefault();

		$("#upload")[0].files = e.originalEvent.dataTransfer.files;
		$("#upload-form").submit()
    });

	$(window).bind("popstate", function(e) {
		dir.clearErrors()
		dir.poppingHistory = true
		dir.refresh(e.originalEvent.state.path)
	})

})
