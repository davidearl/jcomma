$(function(){

	function sorting() {
		$(".clevel1 .clist").sortable({handle: ".cmove2", cancel: "", stop: savespec});
		$(".clevel2 .clist").sortable({handle: ".cmove3", cancel: "", stop: savespec});
		$(".clevel3 .clist").sortable({handle: ".cmove4", cancel: "", stop: savespec});
	}
	sorting();
	
	$("body").on("click", ".cadd", function(e){
		/* pressing one of the '+' buttons copies the corresponding proforma structure to just before the button */
		e.preventDefault(); e.stopPropagation();
		var jself = $(this);
		$("#"+jself.attr("proforma")).clone().removeClass("cproforma").
			removeAttr("id").appendTo(jself.prev("ul.clist"));
		savespec();
		sorting();
	});

	$("body").on("click", ".cremove", function(e){
		/* pressing one of the 'X' buttons removes the group of options containing the button */
		e.preventDefault(); e.stopPropagation();
		var jself = $(this);
		jself.closest(".cgroup").remove();
		savespec();
	});

	$("body").on("change", "select", function(e){
		/* some select choices come with further options; the furtheroptions attribute identifies these. 
		   Make visible those and only those (in the same set of options) for this select */
		var jself = $(this);		
		var f = jself.find("option:selected").attr("furtheroptions");
		if (typeof f != "string") { return; }
		var jparent = jself.closest("div");
		jparent.find(".cfurtheroption").not(jself).hide();
		if (f == "") { return; }
		f.split(",").forEach(function(el,idx){ jparent.find("."+el).show(); });		
	});

	$("body").on("change", "select,input[type=text],input[type=checkbox],textarea", function(e){
		/* always keep the current state in storage so we can restore it on revisiting the page */
		savespec();
	});

	$("body").on("click", "a.chelp", function(e){
		/* show the help panel */
		e.preventDefault(); e.stopPropagation();
		$("#ihelpframe").attr({src: $(this).attr("href")}).closest("#ihelp").css({top: $(window).scrollTop()+70, height: $(window).height()-100}).show();
	});

	$("#ihelpclose").click(function(e){
		/* close the help panel */
		e.preventDefault(); e.stopPropagation();
		$("#ihelp").hide();
	});

	function toggleqif() {
		/* qif format has some differences in the options, particularly the field names, which are fixed */
		var qif = $("#ioutputformat").val() == "qif";
		$(".cqif").toggle(qif);
		$(".cnotqif").toggle(! qif);
	}
	
	$("#ioutputformat").change(toggleqif);
	
	$("#ireset").click(function(e){
		e.preventDefault(); e.stopPropagation();
		loadspec("{\"comment\":\"\",\"outputTo\":\"attachment\",\"outputFormat\":\"json\",\"encoding\":\"auto\",\"headerRows\":0,\"rowCount\":1}");	
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
	
	$("#isubmit").on("click", function(e){
		/* invoke the API - we use the existing form which just
		   contains an input type file, and add to that the single
		   hidden field for the JSON spec which says how to interpret
		   the file */
		$(".csubmithidden").remove();
		$("<input>").attr({type: "hidden", value: makespec(), name: "spec"}).
			addClass("csubmithidden").appendTo("#isubmission");
		$("#isubmission").submit();
	});

	$("#iasaveoptions").click(function(){
		/* rather than invoking the API, just save the options for re-use later */
		$(this).attr({href: "data:application/json;charset=utf-8," + encodeURIComponent(makespec()),
					  download: 'jcomma.json'});
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
				var jparent = js.closest("div");
				jparent.find(".cfurtheroption").hide();
				if (f != "") { f.split(",").forEach(function(el,idx){ jparent.find("."+el).show(); }); }
			}
			j.find("input[type=text],textarea").filter("[name=\""+k+"\"]").val(v).show();
			j.find("input[type=checkbox]").filter("[name=\""+k+"\"]").prop({checked: true}).show();			
		}
	}

	function makespec(){
		/* capture the form content and return as a JSON string (we may save this to a file or localStorage) */
		return JSON.stringify(recurse(1, $("#iform .coptions")));
	}

	function savespec() {
		/* save to localStorage on each change */
		localStorage.spec = makespec();
	}
	
	function loadspec(spec){
		/* given a spec JSON string, set the form fields correspondingly */
		$("#iform .cremove").each(function(idx,el) { $(el).closest(".cgroup").remove(); });
		var spec = JSON.parse(spec);
		if (! spec) { return; }
		$.each(spec, function(k, v){ populate(k, v, $("#iform")); });
		toggleqif();
		sorting();
	}
	
	$("#ialoadoptions").change(function(e1){
		/* load spec from file in response to the choose file button for this */
		var f = $(this)[0].files[0];
		$(this).val("");
		if (f) {
			var r = new FileReader();
			r.onload = function(e2){
				loadspec(r.result);
				localStorage.spec = r.result;
			};
			r.readAsText(f);
		}
	});

	$("#icsv").change(function(e){
		$(".cfilewarning").toggle($(this).val() == "");
	});
	
	/* reload automatically from any previous use (except for the CSV file itself) */
	if ("spec" in localStorage) { loadspec(localStorage.spec); }
});
