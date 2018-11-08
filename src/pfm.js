var dir = {
	path: "",
	sort: "name",
	sort_asc: true,
	popping_history: false,
	pattern: "",
	replace: "",
	refresh: function(path) {
		return $.post({
			url: "?refresh",
			data: {"path": path},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					$("#path").val(this.path)
				}
				else {
					this.update(data)
				}
			}
		})
	},
	update: function(data) {
		if (typeof data != "undefined") { //Allows calls of update() to use existing data
			if (!data.path || !data.subdirs || !data.files) {
				this.error("Could not update directory information from server response")
				return
			}
			this.path = data.path
			this.subdirs = data.subdirs
			this.files = data.files
		}

		if (!this.popping_history) {
			if (location.hash == "" || decodeURIComponent(location.hash.substr(1)) == this.path)
				history.replaceState({path: this.path}, `File Manager - ${this.path}`, "#" + encodeURIComponent(this.path))
			else
				history.pushState({path: this.path}, `File Manager - ${this.path}`, "#" + encodeURIComponent(this.path))
		}
		this.popping_history = false

		this.sortFiles(this.sort, this.sort_asc)
		$("#path").val(this.path)

		$("#subdirs").empty()
		if (this.path != "/")
			$("#subdirs").append($("<tr class='subdir'><td class='subdir'>..</td></tr>"))
		this.subdirs.forEach(function(subdir) {
			$("#subdirs").append($("<tr class='subdir'>").append($("<td class='subdir'>").text(subdir).attr("title", subdir)))
		})

		$("#check-all").prop("checked", false)

		$("#files").empty()
		var file_path = this.path == "/" ? "" : this.path
		this.files.forEach(file => {
			var checkbox = $(`<input class="files" type="checkbox" value="${file.name}">`)
			checkbox.change(function(e) {
				var row = $(this).parent().parent("tr")
				if ($(this).prop("checked"))
					row.addClass("selected")
				else
					row.removeClass("selected")
			})
			checkbox = $("<td class='col-checked'>").append(checkbox)
			var name = $("<td class='col-name'>").append($(`<a href="${this.baseURLPath}${file_path}/${file.name}" target="_BLANK" title="${file.name}">${file.name}</a>`))
			if (!$("#chkbx-name").prop("checked"))
				name.hide()
			var size = $("<td class='col-size'>").text(dir.formatSize(file.size))
			if (!$("#chkbx-size").prop("checked"))
				size.hide()
			var owner = $("<td class='col-owner'>").text(file.owner)
			if (!$("#chkbx-owner").prop("checked"))
				owner.hide()
			var group = $("<td class='col-group'>").text(file.group)
			if (!$("#chkbx-group").prop("checked"))
				group.hide()
			var perms = $("<td class='col-perms'>").text(dir.formatPerms(file.permissions))
			if (!$("#chkbx-perms").prop("checked"))
				perms.hide()
			var created = $("<td class='col-created'>").text(dir.formatDate(file.created))
			if (!$("#chkbx-created").prop("checked"))
				created.hide()
			var modified = $("<td class='col-modified'>").text(dir.formatDate(file.modified))
			if (!$("#chkbx-modified").prop("checked"))
				modified.hide()
			var row = $("<tr>").append(checkbox).append(name).append(size).append(owner).append(group).append(perms).append(created).append(modified)
			$("#files").append(row)
		})
		$("th.files").removeClass("sort_asc").removeClass("sort_desc")
		$("th.files:contains('" + this.sort.substr(1) + "')").addClass(dir.sort_asc ? "sort_asc" : "sort_desc")
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
	makeDir: function(name) {
		if (name == null)
			return
		if (name == "" || name.includes("/")) { //TODO: search for invalid characters by OS
			this.error(`Invalid directory name: ${name}`)
			return
		}
		$.post({
			url: "?make_dir",
			data: {"path": this.path,
				"name": name},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.update(data)
				}
			}
		})
	},
	removeDirs: function(names) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.subdirs.includes(names[i])) {
				this.error(`Directory "${names[i]}" does not exist`)
				return
			}
		}
		
		$.post({
			url: "?remove_dirs",
			data: {"path": this.path,
				"names[]": names},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					if (data.path && data.subdirs && data.files) { //partially successful deletion
						this.update(data)
					}
				}
				else {
					this.update(data)
				}
			}
		})
	},
	renameDir: function(from, to) {
		if (to == null)
			return

		if (!this.subdirs.includes(from)) {
			this.error(`Directory "${from}" does not exist`)
			return
		}

		if (to == "" || to.includes("/")) { //TODO: search for invalid characters by OS
			this.error(`Invalid directory name: ${to}`)
			return
		}

		$.post({
			url: "?rename",
			data: {"path": this.path,
				"from": from,
				"to": to},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.update(data)
				}
			}
		})
	},
	killDirs: function(names) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.subdirs.includes(names[i])) {
				this.error(`Directory "${names[i]}" does not exist`)
				return
			}
		}
		
		$.post({
			url: "?kill_dirs",
			data: {"path": this.path,
				"names[]": names},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					if (data.path && data.subdirs && data.files) { //partially successful deletion
						this.update(data)
					}
				}
				else {
					this.update(data)
				}
			}
		})
	},
	getSelectedSubdirs: function() {
		return $("li.subdir.selected").map(function() {
			return $(this).text()
		})
	},
	regexRenameTest: function(pattern, replace) {
		$.post({
			url: "?regex_rename_test",
			data: {"path": this.path,
				"pattern": pattern,
				"replace": replace
			},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.pattern = pattern
					this.replace = replace
					this.showRegexRenames(data.renames)
				}
			}
		})
	},
	regexRename: function() {
		$.post({
			url: "?regex_rename",
			data: {"path": this.path,
				"pattern": this.pattern,
				"replace": this.replace,
				"exec": true},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.pattern = ""
					this.replace = ""
					this.update(data)
				}
			}
		})
	},
	delete: function(names) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.files.map(file => {return file.name}).includes(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}
		
		$.post({
			url: "?delete",
			data: {"path": this.path,
				"names[]": names},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					if (data.path && data.subdirs && data.files) { //partially successful deletion
						this.update(data)
					}
				}
				else {
					this.update(data)
				}
			}
		})
	},
	moveFiles: function(names, to) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.files.map(file => {return file.name}).includes(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}

		$.post({
			url: "?move",
			data: {"path": this.path,
				"names[]": names,
				"to": to},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					if (data.path && data.subdirs && data.files) { //partially successful deletion
						this.update(data)
					}
				}
				else {
					this.update(data)
				}
			}
		})
	},
	copyFiles: function(names, to) {
		if (names == null)
			return
		for (var i = 0; i < names.length; i++) {
			if (!this.files.map(file => {return file.name}).includes(names[i])) {
				this.error(`File "${names[i]}" does not exist`)
				return
			}
		}

		$.post({
			url: "?copy",
			data: {"path": this.path,
				"names[]": names,
				"to": to},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
					if (data.path && data.subdirs && data.files) { //partially successful deletion
						this.update(data)
					}
				}
				else {
					this.update(data)
				}
			}
		})
	},
	renameFile: function(from, to) {
		if (to == null)
			return

		if (!this.files.map(file => {return file.name}).includes(from)) {
			this.error(`File "${from}" does not exist`)
			return
		}

		if (to == "" || to.includes("/")) { //TODO: search for invalid characters by OS
			this.error(`Invalid file name: ${to}`)
			return
		}

		$.post({
			url: "?rename",
			data: {"path": this.path,
				"from": from,
				"to": to},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.update(data)
				}
			}
		})
	},
	newFile: function(name) {
		$.post({
			url: "?new_file",
			data: {"path": this.path,
				"name": name
			},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.update(data)
				}
			}
		})
	},
	editFile: function(name) {
		if (!this.files.map(file => file.name).includes(name)) {
			this.error(`Could not find file "${file}"`)
			return
		}
		$.post({
			url: "?read_file",
			data: {"path": this.path,
				"name": name
			},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					this.openFile = name
					$("#edit").val(data.contents)
					$("#edit-box").show()
				}
			}
		})
	},
	saveFile: function() {
		return $.post({
			url: "?write_file",
			data: {"path": this.path,
				"name": this.openFile,
				"contents": $("#edit").val()
			},
			dataType: "json",
			success: data => {
				if (data.error || !data.success) {
					this.error(data.error ? data.error : "An unknown error occurred")
				}
				else {
					$(`td.col-name > a:contains("${dir.openFile}")`).parent().parent().find("td.col-size").text(dir.formatSize(data.filesize))
					console.log("Successfully saved file")
				}
			}
		})
	},
	error: function(error) {
		$("#dir-errors").text(error).show()
	},
	clearErrors: function() {
		$("#dir-errors").text("").hide()
	},
	clear: function() {
		$("#path").val("")
		$("ul.subdirs").empty()
		$("#pattern").val("")
		$("#replace").val("")
		$("#edit").val("")

		$("#files").empty()

		this.clearErrors()
	},
	formatSize: function(size) {
		var i = size == 0 ? 0 : Math.floor( Math.log(size) / Math.log(1024));
    	return ( size / Math.pow(1024, i) ).toFixed(2) * 1 + ' ' + ['B', 'KiB', 'MiB', 'GiB', 'TiB'][i];
	},
	formatPerms: function(perms) {
		return perms
	},
	formatDate: function(timestamp) {
		return new Date(timestamp*1000).toISOString().substr(0, 19).replace('T', ' ');
	},
	getCheckedFiles: function() {
		return $("input.files:checked").map(function() { return $(this).val() }).get()
	},
	showRegexRenames: function(renames) {
		$("#regex-rename").hide()
		$("#pattern").prop("disabled", true)
		$("#replace").prop("disabled", true)
		$("#regex-rename-confirm").show()
		$("#regex-rename-clear").show()
		renames.forEach(rename => {
			$(`td.col-name`).filter(function() { 
				return $(this).text() === rename.from
			}).append($(`<span><br />&emsp;&#x27A5; ${rename.to}</span>`))
		})
	},
	clearRegexRenames: function() {
		$("td.col-name > span").remove()
		$("#regex-rename").show()
		$("#regex-rename-confirm").hide()
		$("#regex-rename-clear").hide()
		$("#pattern").prop("disabled", false)
		$("#replace").prop("disabled", false)
		this.pattern = ""
		this.replace = ""
	},
	populateDirSelect: function() {
		var li = $("<li class='dir-select'>").append($("<span class='dir-select'>").text("/"))
		li.append($("<input type='hidden'>").val("/"))
		$("#dir-select > ul.dir-select").empty()
		$("#dir-select > ul.dir-select").append(li)
		this.selectDir($("span.dir-select"))
		$("#clear-cover").show()
		$("#dir-select").show()
	},
	selectDir: function(ele) {
		$("span.dir-select.selected").removeClass("selected")
		ele.addClass("selected")
		if (ele.siblings().length < 2) {
			path = ele.siblings("input").val()
			$.post({
				url: "?get_subdirs",
				data: {"path": path},
				dataType: "json",
				success: data => {
					if (data.error || !data.success) {
						this.error(data.error ? data.error : "An unknown error occurred")
					}
					else if (data.path != path) {
						this.error("Server returned unexpected path")
					}
					else {
						if (path == "/")
							path = ""
						if (data.subdirs.length > 0) {
							ele.parent().append($("<ul class='dir-select'>"))
							ul = ele.siblings().last()
							data.subdirs.forEach(function(subdir) {
								var li = $("<li class='dir-select'>").append($("<span class='dir-select'>").text(subdir))
								li.append($("<input type='hidden'>").val(path + "/" + subdir))
								ul.append(li)
							})
						}
					}
				}
			})
		}
	},
	getSelectedDir: function() {
		return $("span.dir-select.selected").eq(0).siblings("input").val()
	}
}

$(function() {
	$("#header").hide()
	$("#manager").hide()
	$.post({
		url: "?is_logged_in",
		dataType: "json",
		success: function(data) {
			if (data.success) {
				if (history.state !== null) {
					dir.refresh(history.state.path)
				}
				else if (location.hash) {
					dir.refresh(decodeURIComponent(location.hash.substr(1)))
				}
				else {
					dir.refresh("/")
				}
				$("#header").show()
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
					$("#login-errors").text(data.error).show()
				}
				else {
					$("#login-errors").text("").hide()
					$("#login").hide()
					dir.refresh("/").done(function() {
						$("#header").show()
						$("#manager").show()
					})
				}
			}
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
					dir.clear()
					$("#header").hide()
					$("#manager").hide()
					dir.clearErrors()
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
		dir.clearErrors()
		$("div.ribbon").hide()
		$("ul.ribbon li.selected").removeClass("selected")
		switch ($("#subdirs-action").val()) {
			case "go":
				dir.refresh($("#path").val())
				break
			case "up":
				dir.refresh($("#path").val().split("/").slice(0,-1).join("/"))
				break
			case "new":
				dir.makeDir(prompt("Enter new directory name:"))
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
				if (selectedSubdirs.length != 1) {
					dir.error("Only one directory may be renamed at a a time")
				}
				else {
					dir.renameDir(selectedSubdirs[0], prompt("Enter new directory name:"))
				}
				break
			case "kill":
				var selectedSubdirs = dir.getSelectedSubdirs()
				var l = selectedSubdirs.length
				var plural = l > 1 ? "ies" : "y"
				if (selectedSubdirs.length > 0 && confirm(`Do you really want to kill ${l} director${plural}? This cannot be undone`))
					dir.killDirs(selectedSubdirs)
				break
			case "select all":
				if ($("tr.subdir.selected").length == $("tr.subdir").length)
					$("tr.subdir").removeClass("selected")
				else
					$("tr.subdir").addClass("selected")
				break
		}
		e.preventDefault()
	})

	$("#dir-errors").click(function(e) {
		dir.clearErrors()
	})

	$("th.files").click(function(e) {
		var sortby = $(e.target).text().toLowerCase().trim()
		if (dir.sort == sortby)
			dir.sort_asc = !dir.sort_asc
		else
			dir.sort_asc = true
		dir.sort = sortby
		dir.update()
	})

	$("#files-form > input[type='submit']").click(function(e) {
		$("#files-action").val(this.value.toLowerCase())
	})

	$("#files-form").submit(function(e) {
		dir.clearErrors()
		$("div.ribbon").hide()
		$("ul.ribbon li.selected").removeClass("selected")
		switch ($("#files-action").val()) {
			case "delete":
				var files = dir.getCheckedFiles()
				if (files.length < 1)
					dir.error("No files selected for deletion")
				else if (confirm("Do you really want to delete " + files.length + " files? This cannot be undone"))
					dir.delete(files)
				break
			case "rename":
				var files = dir.getCheckedFiles()
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
			case "regex rename":
				$("div.ribbon.regex-rename").show()
				break
			case "kill":
				var l = $("#subdirs").val().length
				var plural = l > 1 ? "ies" : "y"
				if (l > 0 && confirm("Do you really want to kill " + l + " director" + plural + "? This cannot be undone."))
					dir.killDirs($("#subdirs").val())
				break
			case "new":
				dir.newFile(prompt("Enter new file name:"))
				break
			case "edit":
				var checkedFiles = dir.getCheckedFiles()
				if (checkedFiles.length > 1) {
					dir.error("Only one file may be edited at a time")
				}
				else if (!dir.openFile || dir.openFile == "") {
					dir.editFile(checkedFiles[0])
				}
				break
			case "move":
			case "copy":
				if (dir.getCheckedFiles().length < 1) {
					dir.error("No files selected")
				}
				else {
					dir.populateDirSelect()
					dir.dirSelectType = "file"
				}
		}
		e.preventDefault();
	})

	$("#go").click(function(e) {
		dir.refresh($("#path").val())
	})

	$("#edit-form > input[type='submit']").click(function(e) {
		$("#edit-action").val(this.value.toLowerCase())
	})

	$("#edit-form").submit(function(e) {
		dir.clearErrors()
		switch ($("#edit-action").val()) {
			case "save":
				dir.saveFile()
				break
			case "save & close":
				dir.saveFile().then(function() {
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

	$("#regex-rename").click(function(e) {
		if ($("#pattern").val() != "") {
			dir.regexRenameTest($("#pattern").val(), $("#replace").val())
		}
	})

	$("#regex-rename-clear").click(function(e) {
		dir.clearRegexRenames()
	})

	$("#regex-rename-confirm").click(function(e) {
		dir.regexRename()
		dir.clearRegexRenames()
	})

	$("form").submit(function(e) {
		$("td.col-name > span").remove()
		$("#regex-rename").show()
		$("#regex-rename-confirm").hide()
		$("#regex-rename-clear").hide()
		$("#pattern").prop("disabled", false)
		$("#replace").prop("disabled", false)
		$("#clear-cover").hide()
	})

	$("thead.files").contextmenu(function(e) {
		$("#column-context").css({'top':e.pageY,'left':e.pageX}).show()
		$("#clear-cover").show()
		e.preventDefault()
	})

	$("#dir-select-cancel").click(function(e) {
		$("#clear-cover").hide()
		$("#dir-select").hide()
	})

	$("#dir-select-select").click(function(e) {
		if (dir.dirSelectType == "file") {
			if ($("#files-action").val() == "copy")
				dir.copyFiles(dir.getCheckedFiles(), dir.getSelectedDir())
			else if ($("#files-action").val() == "move")
				dir.moveFiles(dir.getCheckedFiles(), dir.getSelectedDir())
		}
		$("#clear-cover").hide()
		$("#dir-select").hide()
	})

	$("#clear-cover").click(function(e) {
		$("#clear-cover").hide()
		$("#column-context").hide()
		$("#dir-select").hide()
		$("ul.ribbon li.selected").removeClass("selected")
		$("div.ribbon").hide()
	})

	$("#clear-cover, #column-context").contextmenu(function(e) {
		e.preventDefault()
	})

	$(".column-sel").click(function(e) {
		var column = ".col-" + $(this).attr("id").split("-")[1]
		if ($(this).prop("checked"))
			$(column).show()
		else
			$(column).hide()
	})

	$("body").keydown(function(e) {
		if (e.key == "Escape") {
			if ($("#clear-cover").is(":visible")) {
				$("#clear-cover").hide()
				$("#column-context").hide()
				$("#dir-select").hide()
				$("ul.ribbon li.selected").removeClass("selected")
				$("div.ribbon").hide()
			}
			else if (dir.pattern != "") {
				dir.clearRegexRenames()
			}
		}
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

	$("#files").on("click", "tr", function(e) {
		if (!$(e.target).is("a,input")) {
			var checkbox = $(this).find("td > input").first()
			if (checkbox.prop("checked")) {
				checkbox.prop("checked", false)
				$(this).removeClass("selected")
			}
			else {
				checkbox.prop("checked", true)
				$(this).addClass("selected")
			}

		}
	})

	$("#subdirs").on("click", "tr", function(e) {
		if (this.className.includes("selected"))
			$(this).removeClass("selected")
		else
			$(this).addClass("selected")
	})

	$("#subdirs").on("dblclick", "tr", function(e) {
		dir.refresh(dir.path + "/" + $(this).text())
		e.preventDefault()
	})

	//Prevent selection on dblclick
	$("#subdirs").mousedown(function(e) {
		e.preventDefault()
	})

	$("#dir-select").on("click", "span", function(e) {
		dir.selectDir($(this))
	})

	$("#files").on("contextmenu", "tr", function(e) {
		alert("CONTEXT")
		e.preventDefault()
	})

	$("ul.ribbon").on("click", "li", function(e) {
		if (this.className.includes("selected")) {
			$("ul.ribbon li.selected").removeClass("selected")
			$("div.ribbon").hide()
		}
		else {
			$("div.ribbon").hide()
			$("div.ribbon." + this.className).show()
			$("ul.ribbon li.selected").removeClass("selected")
			$(this).addClass("selected")
			$("#clear-cover").show()
		}
	})

	$(window).bind("popstate", function(e) {
		dir.clearErrors()
		dir.popping_history = true
    	dir.refresh(e.originalEvent.state.path)
    })

})
