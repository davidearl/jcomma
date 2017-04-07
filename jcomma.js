$(function(){

	function ahem(t, s){
		$('#imessage p').html($('#imessage p').text(s).html().replace(/\n/g, '<br>'));
		$('#imessage').dialog({
			width: 500
		});
		$(".ui-dialog-title").text(t);
	}
	
	function sorting() {
		$(".clevel1 .clist").sortable({handle: ".cmove2", cancel: "", stop: saverecipe});
		$(".clevel2 .clist").sortable({handle: ".cmove3", cancel: "", stop: saverecipe});
		$(".clevel3 .clist").sortable({handle: ".cmove4", cancel: "", stop: saverecipe});
	}
	sorting();
	
	$("body").on("click", ".cadd", function(e){
		/* pressing one of the '+' buttons copies the corresponding proforma structure to just before the button */
		e.preventDefault(); e.stopPropagation();
		var jself = $(this);
		$("#"+jself.attr("proforma")).clone().removeClass("cproforma").
			removeAttr("id").appendTo(jself.prev("ul.clist"));
		saverecipe();
		sorting();
	});

	$("body").on("click", ".cremove", function(e){
		/* pressing one of the 'X' buttons removes the group of options containing the button */
		e.preventDefault(); e.stopPropagation();
		var jself = $(this);
		jself.closest(".cgroup").remove();
		saverecipe();
	});

	$("body").on("change", "select.cinput", function(e){
		/* some select choices come with further options; the furtheroptions attribute identifies these. 
		   Make visible those and only those (in the same set of options) for this select */
		var jself = $(this);		
		var f = jself.find("option:selected").attr("furtheroptions");
		if (typeof f != "string") { return; }
		var jparent = jself.closest(".coptionset");
		jparent.children(".cfurtheroption").not(jself).hide();
		if (f == "") { return; }
		f.split(",").forEach(function(el,idx){ jparent.find("."+el).show(); });		
	});

	$("body").on("change", ".cinput", function(e){
		/* always keep the current state in storage so we can restore it on revisiting the page */
		saverecipe();
		if ($(this).attr("id") == "irecipename") { recipeselectoptions(); }
	});

	$("body").on("click", "a.chelp", function(e){
		/* show the help panel */
		e.preventDefault(); e.stopPropagation();
		$("#ihelpframe").attr({src: $(this).attr("href")}).closest("#ihelp").css({top: $(window).scrollTop()+70, height: $(window).height()-100}).show();
	});

	var isubmissiontop = $("#isubmissioncontainer").offset().top;

	$(window).scroll(function(e){
		$("#isubmissioncontainer").toggleClass("ctoplocked", $(window).scrollTop()+10 > isubmissiontop);
	})
	
	$("#ihelpclose").click(function(e){
		/* close the help panel */
		e.preventDefault(); e.stopPropagation();
		$("#ihelp").hide();
	});

	function highlightbadfields(){
		$("#iform").find("select,.cfieldname,.crecordiffield,.cignorerowsname").each(function(idx,el){
			$(this).toggleClass("cbadfield", $(this).val() == "");
		});
	}
	
	function toggleqif() {
		/* qif format has some differences in the options, particularly the field names, which are fixed */
		var qif = $("#ioutputformat").val() == "qif";
		$(".cqif").toggle(qif);
		$(".cnotqif").toggle(! qif);
	}
	
	$("#ioutputformat").change(toggleqif);

	var prefix = "recipe=";
	function recipestoragename(name) { return prefix+name; }
	function getlocalrecipejson(name){ return localStorage[recipestoragename(name)]; }
	function putlocalrecipejson(name, recipe){ localStorage[recipestoragename(name)] = recipe; }
	function haslocalrecipe(name){ return (recipestoragename(name) in localStorage); }
	function recipenames(){
		var names = [];
		$.each(localStorage, function(k, v){
			if (k.substr(0, prefix.length) != prefix) { return; }
			names.push(k.substr(prefix.length));
		});
		return names.sort(function(a,b){ return a.toLowerCase().localeCompare(b.toLowerCase()); });
	}

	
	$("#iloadselect").change(function(e){
		var name = $(this).val();
		localStorage.currentRecipe = name;
		loadrecipe(getlocalrecipejson(name));
		recipeselectoptions();
	});
	
	var defaultrecipe = JSON.stringify({
		recipeName: "(anonymous)",
		comment: "",
		outputTo: "attachment",
		outputFormat: "json",
		encoding: "auto",
		headerRows: 0,
		rowCount: 1
	});
	
	$("#ireset").click(function(e){
		e.preventDefault(); e.stopPropagation();
		loadrecipe(defaultrecipe);
	});

	$("#iexample").click(function(e){
		e.preventDefault(); e.stopPropagation();
		$.getJSON("/Example.jcomma.json", function(j){ $("#ipasterecipe").val(JSON.stringify(j)).change(); });
	});

	$("#ideleterecipe").click(function(e){
		e.preventDefault(); e.stopPropagation();
		var name = $("#irecipename").val();
		if (name == "") { ahem("Anon", "Your recipe is anonymous"); return; }
		if (! haslocalrecipe(name)) { ahem("Not stored", "Your recipe is not stored"); return; }
		delete localStorage[recipestoragename(name)];
		loadrecipe(defaultrecipe);
		recipeselectoptions();
	});

	$("#idelimitertab").change(function(e){
		$("#idelimiter").val($(this).is(":checked") ? "\t" : ",");
		saverecipe();
	});
	$("#idelimiter").change(function(e){
		$("#idelimitertab").prop({checked: $(this).val() == "\t"});
	});
	
	function recurse(level, jel){
		/* use the DOM structure to create a corresponding JSON object to send to the API */
		var j = {};
		jel.find(".cinput"+level+":visible").each(function(idx,el){
			var v = $(el).val();
			if (v == "" || ($(el).attr("type") == "checkbox" && ! $(el).is(":checked"))) { return; }
			if ($(el).hasClass("cint")) { v = parseInt(v); }
			j[$(el).attr("name")] = v;
		});
		jel.find(".clevel"+(level+1)).each(function(idx,el){
			var name = $(el).attr("name");
			if (! (name in j)) { j[name] = []; }
			j[name].push(recurse(level+1, $(el)));
		});
		return j;
	}	
	
	$("#isubmission").on("submit", function(e){
		/* invoke the API - we use the existing form which just
		   contains an input type file, and add to that the single
		   hidden field for the JSON recipe which says how to interpret
		   the file */
		if ($("#icsv").val() == "" && $("#icsvpaste").val() == "") {
			e.preventDefault();
			ahem("No CSV", "You need to choose a csv file to process");
			return;
		} else if ($("#icsv").val() != "" && $("#icsvpaste").val() != "") {
			e.preventDefault();
			ahem("Both file and paste set", "choose either a csv file to process or paste one, but not both (to clear a previously chosen file, click Choose file, then Cancel)");
			return;
		}

		highlightbadfields();
		if ($(".cbadfield").length > 0) {
			e.preventDefault();
			ahem("Empty entry", "You have an empty entry (highlighted in red)");
			return;
		}
		$("#isendrecipe").val(makerecipe());
		// and continue to submit		
	});

	$("#isaverecipe").click(function(){
		/* rather than invoking the API, just save the options for re-use later */
		var name = $("#irecipename").val();
		if (name == "") { name = "(anonymous)"; }
		var s = makerecipe();
		if ($("#icopyrecipepretty").is(":checked")) {		
			s = JSON.stringify(JSON.parse(s), null, 2);
		}
		$(this).attr({href: "data:application/json;charset=utf-8," + encodeURIComponent(s),
					  download: name+".jcomma.json"});
	});

	function populate(k, v, j){
		/* recursively fill in the form programmatically */
		if ($.isArray(v)) {
			$.each(v, function(idx, va){ populate(k, va, j); });
		} else if (typeof v == 'object') {
			var jp = $(".cproforma[name=\""+k+"\"]");
			var jb = j.find("button[proforma=\""+jp.attr("id")+"\"]");
			var jo = jp.clone().removeClass("cproforma").removeAttr("id").appendTo(jb.prev("ul.clist"));
			$.each(v, function(ko, vo){	populate(ko, vo, jo); });
		} else {
			var js = j.find("select").filter("[name=\""+k+"\"]").val(v).show();
			var f = js.find("option:selected").attr("furtheroptions");
			if (typeof f == "string") {
				var jparent = js.closest(".coptionset");
				jparent.children(".cfurtheroption").hide();
				if (f != "") { f.split(",").forEach(function(el,idx){ jparent.find("."+el).show(); }); }
			}
			j.find("input[type=text],textarea").filter("[name=\""+k+"\"]").val(v).show();
			j.find("input[type=checkbox]").filter("[name=\""+k+"\"]").prop({checked: true}).show();			
		}
	}

	function makerecipe(){
		/* capture the form content and return as a JSON string (we may save this to a file or localStorage) */
		var data = recurse(1, $("#iform .coptions"));
		data.recipeVersion = 3;
		return JSON.stringify(data);
	}

	function saverecipe() {
		/* save to localStorage and the copy+paste field on each change */
		var name = $("#irecipename").val();
		if (name == "") { name = "(anonymous)"; }
		localStorage.currentRecipe = name;
		putlocalrecipejson(name, makerecipe());
		var s = localStorage[recipestoragename(name)];
		if ($("#icopyrecipepretty").is(":checked")) {
			s = JSON.stringify(JSON.parse(s), null, 2);
		}
		$("#icopyrecipe").val(s);
	}
	
	function loadrecipe(recipejson){
		/* given a recipe JSON string, set the form fields correspondingly */
		$("#iform .cremove").each(function(idx,el) { $(el).closest(".cgroup").remove(); });
		var recipe;
		try {
			recipe = JSON.parse(recipejson);
			if (! recipe) { throw {message: "invalid JSON"}; }
		} catch (e){
			ahem("Bad JSON",
				 "Incorrect JSON in recipe being loaded (note: you can check JSON for errors at http://jsonlint.com/). JSON says:\n\n"+e.message);
			return;
		}
		if (! ("recipeName" in recipe)) { recipe.recipeName = "(anonymous)"; }
		if (! ("delimiterChar" in recipe)) { recipe.delimiterChar = ','; }
		if (! ("enclosureChar" in recipe)) { recipe.enclosureChar = '"'; }
		$.each(recipe, function(k, v){ populate(k, v, $("#iform")); });
		$("#idelimitertab").prop({checked: $("#idelimiter").val() == "\t"});
		toggleqif();
		sorting();
		$("#icopyrecipe").val(recipejson);
		localStorage.currentRecipe = recipe.recipeName;
		$("#iyourrecipe").fadeOut(100, function(){ $(this).fadeIn(400); });
	}

	function recipeselectoptions(){
		var jselect = $("#iloadselect").empty();
		$("<option>").prop({readonly: true}).text("choose one").appendTo(jselect);
		$.each(recipenames(), function(idx, name){
			$("<option>").text(name).appendTo(jselect);
		});
	}
	
	$("#iloadrecipe").change(function(e1){
		/* load recipe from file in response to the choose file button for this */
		var f = $(this)[0].files[0];
		$(this).val("");
		if (f) {
			var r = new FileReader();
			r.onload = function(e2){
				loadrecipe(r.result);
				saverecipe();
				recipeselectoptions();
			};
			r.readAsText(f);
		}
	});

	$("#iloadrecipeurl").change(function(e){
		var encodedurl = encodeURIComponent($(this).val());
		if (encodedurl == "") { return; }
		window.history.pushState({}, "", ".?recipe="+encodedurl);
		getcloudrecipejson(encodedurl);
	});

	$("#ipasterecipe").change(function(e1){
		/* load recipe from contents */		
		loadrecipe($(this).val());
		$(this).val("done")
	});

	$("#icopyrecipe,#ipasterecipe").focus(function(){
		$(this).select();
	});

	$("#icopyrecipepretty").change(function(e){
		saverecipe();
	})

	
	function getcloudrecipejson(encodedurl){
		$.ajax("/corsproxy.php?recipe="+encodedurl, {
			dataType: "json",
			method: 'GET',
			success: function(j){
				loadrecipe(JSON.stringify(j));
				saverecipe();
			},
			error: function(xhr, s, err){ ahem("Bad URL", "cannot fetch recipe from your given recipe URL"); }
		});		
	}
	
	/* reload automatically from any previous use (except for the CSV file itself) */
	var fromURL = false;
	if (window.location.href.indexOf('?') !== -1) {		
		var ps = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
		$.each(ps, function(idx, p){
			var kv = p.split("=");
			if (kv[0] != "recipe") { return; }
			fromURL = true;
			$("#iloadrecipeurl").val(decodeURIComponent(kv[1]));
			getcloudrecipejson(kv[1]);
			return false;
		});
	}
	if (! fromURL && ("currentRecipe" in localStorage)) {
		loadrecipe(getlocalrecipejson(localStorage.currentRecipe));
	}
	recipeselectoptions();

	setTimeout(highlightbadfields, 250);
});
