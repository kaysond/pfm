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
			$("#subdirs").append($("<option>").val("..").text(".."))
		this.subdirs.forEach(function(subdir) {
			$("#subdirs").append($("<option>").val(subdir).text(subdir))
		})

		$("#check-all").prop("checked", false)

		$("#files").empty()
		var file_path = this.path == "/" ? "" : this.path
		this.files.forEach(file => {
			var checkbox = $("<td>").append($(`<input class="files" type="checkbox" value="${file.name}">`))
			var name = $("<td class='col-name'>").append($(`<a href="${file_path}/${file.name}" target="_BLANK" title="${file.name}">${file.name}</a>`))
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
			url: "?rename_dir",
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
	smartRenameTest: function(pattern, replace) {
		$.post({
			url: "?smart_rename_test",
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
					this.showSmartRenames(data.renames)
				}
			}
		})
	},
	smartRename: function() {
		$.post({
			url: "?smart_rename",
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
			url: "?rename_file",
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
					//Should toast!
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
		$("#subdirs").empty()
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
	showSmartRenames: function(renames) {
		$("#smart-rename").hide()
		$("#pattern").prop("disabled", true)
		$("#replace").prop("disabled", true)
		$("#smart-rename-confirm").show()
		$("#smart-rename-clear").show()
		renames.forEach(rename => {
			$(`td.col-name:contains("${rename.from}")`).append($(`<span><br />&emsp;&#x27A5; ${rename.to}</span>`))
		})
	},
	clearSmartRenames: function() {
		$("td.col-name > span").remove()
		$("#smart-rename").show()
		$("#smart-rename-confirm").hide()
		$("#smart-rename-clear").hide()
		$("#pattern").prop("disabled", false)
		$("#replace").prop("disabled", false)
		this.pattern = ""
		this.replace = ""
	}
}

$(function() {
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
			data: {"username":"files","password":$("#password").val()},
			dataType: "json",
			success: function(data) {
				if (data.error || !data.success) {
					$("#login-errors").text(data.error).show()
				}
				else {
					$("#login-errors").text("").hide()
					$("#login").hide()
					dir.refresh("/").done(function() {
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
				if ($("#subdirs").val().length > 0)
					dir.removeDirs($("#subdirs").val())
				break
			case "rename":
				var subdir_selection = $("#subdirs").val()
				if (subdir_selection.length != 1) {
					dir.error("Only one directory may be renamed at a a time")
				}
				else {
					dir.renameDir(subdir_selection[0], prompt("Enter new directory name:"))
				}
				break
			case "kill":
				var l = $("#subdirs").val().length
				var plural = l > 1 ? "ies" : "y"
				if (l > 0 && confirm("Do you really want to kill " + l + " director" + plural + "? This cannot be undone."))
					dir.killDirs($("#subdirs").val())
				break
			case "smart rename":
				if ($("#pattern").val() != "") {
					dir.smartRenameTest($("#pattern").val(), $("#replace").val())
				}
				break
		}
		e.preventDefault()
	})

	$("#subdirs").dblclick(function(e) {
		if ($(e.target).is("option")) {
			dir.clearErrors()
			dir.refresh($("#path").val() + "/" + $(e.target).val())
			$("#subdirs").blur()
		}
	})

	$("#subdirs").keyup(function(e) {
		if (e.key == "Enter" && $("#subdirs").val().length > 0) {
			dir.clearErrors()
			dir.refresh($("#path").val() + "/" + $("#subdirs").val()[0])
			$("#subdirs").blur()
		}
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
		switch ($("#files-action").val()) {
			case "delete":
				var files = dir.getCheckedFiles()
				if (files.length > 0 && confirm("Do you really want to delete " + files.length + " files? This cannot be undone"))
					dir.delete(files)
				break
			case "rename":
				var files = dir.getCheckedFiles()
				if (files.length != 1) {
					dir.error("Only one file may be renamed at a time")
				}
				else {
					dir.renameFile(files[0], prompt("Enter new file name:"))
				}
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
		}
		e.preventDefault();
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

	$("#smart-rename-clear").click(function(e) {
		dir.clearSmartRenames()
		e.preventDefault()
	})

	$("#smart-rename-confirm").click(function(e) {
		dir.smartRename()
		dir.clearSmartRenames()
		e.preventDefault()
	})

	$("form").submit(function(e) {
		$("td.col-name > span").remove()
		$("#smart-rename").show()
		$("#smart-rename-confirm").hide()
		$("#smart-rename-clear").hide()
		$("#pattern").prop("disabled", false)
		$("#replace").prop("disabled", false)
	})

	$("thead.files").contextmenu(function(e) {
		$("#column-context").css({'top':e.pageY,'left':e.pageX}).show()
		$("#clear-cover").show()
		e.preventDefault()
	})

	$("#clear-cover").click(function(e) {
		$("#clear-cover").hide()
		$("#column-context").hide()
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
			}
			else if (dir.pattern != "") {
				dir.clearSmartRenames()
			}
		}
	})

	$("#check-all").change(function(e) {
			$("input[type='checkbox']").prop("checked", $("#check-all")[0].checked)
	})

	$("#files").on("click", "tr", function(e) {
		if (!$(e.target).is("a,input")) {
			var checkbox = $(this).find("td > input").first()
			checkbox.prop("checked", !checkbox.prop("checked"))
		}
	})

	$(window).bind("popstate", function(e) {
		dir.clearErrors()
		dir.popping_history = true
    	dir.refresh(e.originalEvent.state.path)
    })

})