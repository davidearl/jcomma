<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>jcomma: a CSV converter</title>
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,400i,700,700i" rel="stylesheet">
<link rel='stylesheet' href='jcomma.css'>
<link rel='stylesheet' href='jquery-ui-1.12.1/jquery-ui.css'>
<link rel='shortcut icon' href='logo.png'>
<script src='jquery-3.1.0.min.js'></script>
<script src='jquery-ui-1.12.1/jquery-ui.min.js'></script>
<script src='jcomma.js'></script>
</head>
<body>
  <h1><img src='logo.svg' alt='logo'> jcomma: flexible conversion and sanitization of CSV files</h1>
  <a href="https://github.com/davidearl/jcomma"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/e7bbb0521b397edbd5fe43e7f760759336b5e05f/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f677265656e5f3030373230302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_green_007200.png"></a>
  <p><span style='color: tomato; font-weight: bold;'>Beta testing!</span> This is work in progress.</p>
  <p>jcomma can be used:</p>
  <ul class='cbulleted'>
	<li><a class='chelp' href='help.php?a=husewebapp'>manually</a>, from here or another installation</li>
	<li>programatically via its <a class='chelp' href='help.php?a=huseapi'>API</a></li>
	<li>as a <a class='chelp' href='help.php?a=huselibrary'>library</a> integrated into another product</li>
	<li>in a Linux or Windows <a class='chelp' href='help.php?a=huseshell'>shell command</a> or pipe</li>
  </ul>
  <p><a href='help.php' target='jcommahelp'>Help</a> | <a href='https://github.com/davidearl/jcomma/blob/master/LICENSE'>MIT License</a> | <a href='https://github.com/davidearl/jcomma'>On GitHub</a></p>
  <hr>

  <div>
	Load settings from file <a class='chelp cinfo' href='help.php?a=hloadsettings'></a>: <input type='file' id='ialoadoptions'>
  </div>

  <div>
	<a href='#' id='ireset'>reset settings</a> <a class='chelp cinfo' href='help.php?a=hresetsettings'></a>
  </div>
  
  <div id='iform'>
	<div class='csection coptions clevel1'>
	  <label class='clabelheader' for='icomment'>Comment/description:</label> (just for your own information) <a class='chelp cinfo' href='help.php?a=hcomment'></a><br>
	    <textarea id='icomment' class='cinput1' name='comment'></textarea>	    
	</div>
	
	<div class='csection coptions clevel1'>
	  <label class='clabelheader'>Output to:</label> <a class='chelp cinfo' href='help.php?a=houtputto'></a><br>
	  <select id='ioutputto' class='cinput1' name='outputTo'>
		<option value='attachment' selected='selected'>file download</option>
		<option value='inline'>browser tab</option>
	  </select>
	</div>
	
	<div class='csection coptions clevel1'>
	  <label class='clabelheader'>Output format:</label> <a class='chelp cinfo' href='help.php?a=houtputformat'></a><br>
	  <select id='ioutputformat' name='outputFormat' class='cinput1'>
		<option value='json' selected='selected' furtheroptions='coutputformatjson'>json</option>
		<option value='csv' furtheroptions='coutputformatcsv'>csv</option>
		<option value='xlsx' furtheroptions='coutputformatxlsx'>xlsx (Excel 2007)</option>
		<option value='html' furtheroptions='coutputformathtml'>html</option>
		<option value='xml' furtheroptions='coutputformatxml'>xml</option>
		<option value='qif' furtheroptions='coutputformatqif'>qif</option>
	  </select>

	  <span class='coutputformatjson cfurtheroption' >
		<input type='checkbox' id='ioutputstyle' class='cinput1' name='outputStyle' value='pretty'>
	      <label for='ioutputstyle'>pretty print</label>
		<input type='checkbox' id='ioutputbulkelastic' class='cinput1' name='outputBulkElastic' >
	      <label for='ioutputbulkelastic'>bulk data for elasticsearch</label>
		<input type='text' id='ioutputjsonname' class='cinput1' name='outputName' placeholder='type name (optional)'>
	  </span>
	  
	  <span class='coutputformatcsv cinitiallyhidden cfurtheroption'>
		<label for='ioutputcsvencoding'>output encoding: </label>
		<select id='ioutputcsvencoding' name='outputEncoding' class='cinput1'>
		  <option value='Windows-1251' selected>Windows-1251 for Excel</option>
		  <option value='UTF-8'>UTF-8 for e.g. Google Sheets</option>
		</select>
		<input type='checkbox' id='ioutputcsvheaderrow' class='cinput1' name='outputHeaderRow' value='true'>
	      <label for='ioutputcsvheaderrow'>include header row</label>
	  </span>
	  
	  <span class='coutputformatxlsx cinitiallyhidden cfurtheroption'>
		<input type='checkbox' id='ioutputxlsxheaderrow' class='cinput1' name='outputHeaderRow' value='true'>
	      <label for='ioutputxlsxheaderrow'>include header row</label>
	  </span>
	  
	  <span class='coutputformathtml cinitiallyhidden cfurtheroption'>
		<input type='checkbox' id='ioutputhtmlxheaderrow' class='cinput1' name='outputHeaderRow' value='true'>
	      <label for='ioutputhtmlheaderrow'>include header row</label>
	  </span>
	  
	  <span class='coutputformatxml cinitiallyhidden cfurtheroption' >
		<input type='checkbox' id='ioutputxmlelements' class='cinput1' name='outputXMLElements' value='true'>
	      <label for='ioutputxmlelements'>values as elements rather than attributes</label>
		<input type='text' id='ioutputxmlname' class='cinput1' name='outputName' placeholder='element name (optional)'>
	  </span>
	  
	  <span class='coutputformatqif cinitiallyhidden cfurtheroption' >
        Transaction types:
        <select id='ioutputqiftype' class='cinput1' name='outputQIFType'>
          <option value='Bank'>Cash flow: current account</option>
          <option value='Cash'>Cash flow: cash</option>
          <option value='CCard'>Cash flow: credit card</option>
          <option value='Invst'>Investment account</option>
		  <option value='Oth A'>Property &amp; Debt: Asset</option>
		  <option value='Oth L'>Property &amp; Debt: Liability</option>
		  <option value='Invoice'>Invoice</option>
        </select>
	  </span>
	</div>
	
	<div class='csection coptions'>
	  <label class='clabelheader'>CSV Input:</label><br>
	  <label for='icsv'>CSV File:</label> <a class='chelp cinfo' href='help.php?a=hcsv'></a>
	  <form id='isubmission' action='jcomma.php' class='csubmission' method='POST' target='jcommaresult' enctype='multipart/form-data'>
	    <input type='file' id='icsv' name='csv'>
	  </form><br>
	  <label for='iencoding'>Input CSV encoding:</label> <a class='chelp cinfo' href='help.php?a=hencoding'></a>
		<select id='iencoding' class='cinput1' name='encoding'>
		  <option value='auto'>attempt to detect automatically</option>
		  <option value='UTF-8'>UTF-8 (Google Sheets)</option>
		  <option value='Windows-1250'>Windows cp1250 (typically Excel)</option>
		  <option value='ISO-8859-1'>Latin1 (ISO-8859-1)</option>  
		</select>
	</div>
	
	<div class='csection coptions clevel1'>
	  <label for='iheaderrows'>Header rows:</label> <a class='chelp cinfo' href='help.php?a=hheaderrows'></a>
	  <input type='text' class='cshortinput cint cinput1' id='iheaderrows' name='headerRows' pattern='[0-9]+' value='0'> (last header row entries can be used to refer to columns below)<br>
	  <!-- possible enhancement: header rows until or while condition -->
	</div>
	
	<div class='csection coptions clevel1'>
	  <label for='irowcount'>Each record formed from </label> 
	    <input type='text' class='cshortinput cint cinput1' id='irowcount' name='rowCount' pattern='[0-9]+' title='digits only' value='1'> rows of the CSV <a class='chelp cinfo' href='help.php?a=hrowcount'></a>
	</div>

	<div class='csection coptions clevel1'>
	  <label class='clabelheader'>Ignore rows in CSV in any of these cases:</label> (except in header rows) <a class='chelp cinfo' href='help.php?a=hignorerows'></a><br>
	  <ul class='clist'></ul>
	  <button id='iaddignorerows' class='cadd' proforma='iignorerowsproforma'>+</button> <span class='canother'>another ignore row condition</span>
	</div>

  	<div class='csection coptions clevel1'>
	  <label class='clabelheader'>Output records</label> (you can make more than one record from each group of rows from the CSV)<br>
	  <ul class='clist'></ul>
	  <button id='iaddrecord' class='cadd' proforma='irecordproforma'>+</button> <span class='canother'>another record</span>
	</div>

    <div class='csection clevel1'>
	  <input type='submit' id='isubmit' value='Do it!'> (in new tab when not downloaded)
	  <span class='cfilewarning'>No CSV file currently selected!</span>
	</div>
  </div>

  <ul class='cproformascontainer cinitiallyhidden'>	
	<li class='cproforma cgroup clevel2 cignorerows' id='iignorerowsproforma' name='ignoreRows'>
	  <button class='cmove cmove2'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='cgroup'>	
		<select class='cignorerowstype cinput2' name='item'>
		  <option value='column'>If column...</option>
		  <option value='field'>If field...</option>
		</select>
		<input type='text' class='cignorerowsname cinput2' name='name' placeholder='column letter/header or field name'>
		<div class='cfurtheroptions'>
		  <select class='cignorerowscondition cinput2' name='condition'>
			<option value='empty' selected='selected' furtheroptions=''>empty (no text at all)</option>
			<option value='white' furtheroptions=''>whitespace only or empty</option>
			<option value='match' furtheroptions='cignorerowsvalue'>matches regular expression</option>
			<option value='nomatch' furtheroptions='cignorerowsvalue'>does not match regular expression</option>
			<option value='eq' furtheroptions='cignorerowsvalue'>equal to</option>
			<option value='ne' furtheroptions='cignorerowsvalue'>not equal to</option>
            <option value='ge' furtheroptions='cignorerowsvalue'>greater or equal to:</option>
            <option value='le' furtheroptions='cignorerowsvalue'>less or equal to:</option>
            <option value='before' furtheroptions='cignorerowsvalue'>before (date):</option>
            <option value='after' furtheroptions='cignorerowsvalue'>after (date):</option>
		  </select>
		  <input type='text' class='cignorerowsvalue cinitiallyhidden cfurtheroption cinput2' name='value' placeholder='value to compare with'>
		  <a class='chelp cinfo' href='help.php?a=hconditions'></a>
		</div>
	  </div>
	</li>

	<li class='cproforma cgroup clevel2 crecords' id='irecordproforma' name='records'>
	  <button class='cmove cmove2'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='coptions cgroup' name='fields'>
		<label class='clabelheader'>Fields of output record:</label>
		  <a class='chelp cinfo' href='help.php?a=hfields'></a><br>
   		<ul class='clist'></ul>
		<button id='iaddfield' class='cadd' proforma='ifieldproforma'>+</button> <span class='canother'>another field</span>
	  </div>

	  <div class='coptions cgroup'>
		<label class='clabelheader'>Don't output record in any of theses cases:</label>
		  <a class='chelp cinfo' href='help.php?a=hunless'></a><br>
  		<ul class='clist'></ul>
		<button id='iaddrecordif' class='cadd' proforma='irecordifproforma'>+</button> <span class='canother'>another "don't output record" condition</span>
	  </div>	
	</li>

	<li class='cproforma clevel3 cgroup crecordsif' id='irecordifproforma' name='unless'>
	  <button class='cmove cmove3'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='coptions cgroup'>
		<label>if field </label>
		  <input type='text' class='crecordiffield cinput3' name='field' placeholder='field name'>
		<select class='crecordifcondition cinput3' name='condition'>
		  <option value='empty' selected='selected' furtheroptions=''>empty (no text at all)</option>
		  <option value='white' furtheroptions=''>whitespace only or empty</option>
		  <option value='match' furtheroptions='crecordifvalue'>matches regular expression</option>
		  <option value='nomatch' furtheroptions='crecordifvalue'>does not match regular expression</option>
		  <option value='eq' furtheroptions='crecordifvalue'>equal to</option>
		  <option value='ne' furtheroptions='crecordifvalue'>not equal to</option>
		  <option value='ge' furtheroptions='crecordifvalue'>greater or equal to</option>
		  <option value='le' furtheroptions='crecordifvalue'>less or equal to</option>
          <option value='before' furtheroptions='crecordifvalue'>before (date):</option>
          <option value='after' furtheroptions='crecordifvalue'>after (date):</option>
		</select>
		<input type='text' class='crecordifvalue cinitiallyhidden cfurtheroption cinput3' name='value' placeholder='value to compare with'> <a class='chelp cinfo' href='help.php?a=hconditions'></a>
	  </div>
	</li>

	<li class='cproforma cgroup clevel3 cfields calongside' id='ifieldproforma' name='fields'>
	  <button class='cmove cmove3'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='coptions cgroup'>
		<label>field name:</label> <a class='chelp cinfo' href='help.php?a=hname'></a> <input type='text' class='cfieldname cinput3 cnotqif' name='name' placeholder='field name'>
		<!-- alternatively for QIF, there is a fixed set of fields: -->
		<select name='name' class='cfieldname cinput3 cqif'>
		  <option value='D'>D: Date</option>
		  <option value='T'>T: Amount</option>
		  <option value='M'>M: Memo</option>
		  <option value='C'>C: Cleared status: blank (not cleared), "*" or "c" (cleared) and "X" or "R" (reconciled).</option>
		  <option value='N'>N: Cheque number or "Deposit", "Transfer", "Print", "ATM", "EFT".</option>
		  <option value='P'>P: Payee, or description for deposits, transfers, etc.</option>
		  <option value='A'>A: Address of Payee. Up to 5 address lines allowed.</option>
		  <option value='L'>L: Category or Transfer and (optionally) Class.</option>
		  <option value='F'>F: Flag as reimbursable business expense.</option>
		  <option value='S'>S: Split category (same as L)</option>
		  <option value='E'>E: Split memo</option>
		  <option value='$'>$: Amount for split item</option>
		  <option value='%'>%: Percent. Used if splits are done by percentage</option>
		  <option value='N'>N: Investment Action (Buy, Sell, etc.)</option>
		  <option value='Y'>Y: Security name</option>
		  <option value='I'>I: Price (investment)</option>
		  <option value='Q'>Q: Quantity of shares</option>
		  <option value='O'>O: Commission cost</option>
		  <option value='$'>$: Amount transferred</option>
		</select>
		<br>
		<label class='clabelheader'>Field concatenated from:</label>
		  <a class='chelp cinfo' href='help.php?a=hcomprising'></a><br>
		  <ul class='clist'></ul>
		  <button id='iaddfieldcomprising' class='cadd' proforma='ifieldcomprisingproforma'>+</button> <span class='canother'>another column or text</span>
		<br>
		<label class='clabelheader'>Field options:</label> (applied in order)
		  <a class='chelp cinfo' href='help.php?a=hoptions'></a><br>
		  <ul class='clist'></ul>
		  <button id='iaddfieldoption' class='cadd' proforma='ifieldoptionproforma'>+</button> <span class='canother'>another option</span>
	  </div>
	</li>

	<li class='cproforma  cgroup clevel4 cfieldoptions' id='ifieldoptionproforma' name='options'>
	  <button class='cmove cmove4'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='coptions cgroup'>
		<div class='cfurtheroptions'>
		  <select class='cfieldoptionitem cinput4' name='item'>
			<option value=''>choose&hellip;</option>
			<option value='ignoreCurrency' furtheroptions='cfieldoptioncurrencies'>ignore currency symbols as follows</option>
			<option value='bookkeepersNegative' furtheroptions=''>treat '(1.23)' or '1.23-' as negative: '-1.23'</option>
			<option value='trim' furtheroptions=''>trim surrounding white space</option>
			<option value='replaceString' furtheroptions='cfieldoptioninputstring,cfieldoptionoutput'>replace all occurences of string</option>
			<option value='replaceRegExp' furtheroptions='cfieldoptioninputregexp,cfieldoptionoutput'>replace using regular expression:</option>
			<option value='convertToNumber' furtheroptions='cfieldoptionconverterror,cfieldoptionnegate'>output as number</option>
			<option value='convertToDate' furtheroptions='cfieldoptionconverterror,cfieldoptionconvertdateformat,cfieldoptionconverttime'>output as ISO date</option>
			<option value='convertToCustomDate' furtheroptions='cfieldoptionconverterror,cfieldoptionconvertdateformat,cfieldoptionconvertdatestyle'>output as custom date</option>
			<option value='omitIf' furtheroptions='cfieldoptioncondition'>omit field if:</option>
			<option value='errorOnValue' furtheroptions='cfieldoptioncondition'>stop with error if value: </option>
		  </select>
		  <input type='text' class='cfieldoptioncurrencies cinitiallyhidden cfurtheroption cinput4' name='currencies' value='&pound;&dollar;&yen;&euro;' placeholder='list of currency symbols'>
		  <input type='text' class='cfieldoptioninputstring cinitiallyhidden cfurtheroption cinput4' name='matches' value='' placeholder='string to replace'>
		  <input type='text' class='cfieldoptioninputregexp cinitiallyhidden cfurtheroption cinput4' name='matches' value='' placeholder='regexp'>
		  <input type='text' class='cfieldoptionoutput cinitiallyhidden cfurtheroption cinput4' name='output' value='' placeholder='replacement'>
		  <div class='cfurtheroptions cfieldoptioncondition cinitiallyhidden cfurtheroption'>
			<select class='cinput4' name='condition'>
			  <option value='empty' selected='selected' furtheroptions=''>empty (no text at all)</option>
			  <option value='white' furtheroptions=''>whitespace only or empty</option>
			  <option value='match' furtheroptions='cfieldoptionvalue'>matches regular expression</option>
			  <option value='nomatch' furtheroptions='cfieldoptionvalue'>does not match regular expression</option>
			  <option value='eq' furtheroptions='cfieldoptionvalue'>equal to</option>
			  <option value='ne' furtheroptions='cfieldoptionvalue'>not equal to</option>
			  <option value='ge' furtheroptions='cfieldoptionvalue'>greater or equal to</option>
			  <option value='le' furtheroptions='cfieldoptionvalue'>less or equal to</option>
              <option value='before' furtheroptions='cfieldoptionvalue'>before (date):</option>
              <option value='after' furtheroptions='cfieldoptionvalue'>after (date):</option>
			</select> 
			<input type='text' class='cfieldoptionvalue cinitiallyhidden cfurtheroption cinput4' name='value' value='' placeholder='compared with'>
		  </div>
		  <input type='text' class='cfieldoptionconvertdatestyle cinitiallyhidden cfurtheroption cinput4' name='dateFormatStyle' value='' placeholder='date style'>
		  <span class='cfieldoptionconverterror cinitiallyhidden cfurtheroption'>
			<input type='checkbox' class='cinput4' name='errorOnType' value='true'> stop on conversion error
		  </span>
		  <span class='cfieldoptionnegate cinitiallyhidden cfurtheroption'>
			<input type='checkbox' class='cinput4' name='negate' value='true'> negate after conversion
		  </span>
		  <span class='cfieldoptionconvertdateformat cinitiallyhidden cfurtheroption'>
			<input type='checkbox' class='cinput4' name='dateFormatUS' value='true'> US dates: e.g. treat 3/4/2016 as March 4
		  </span>
		  <span class='cfieldoptionconverttime cinitiallyhidden cfurtheroption'>
			<input type='checkbox' class='cinput4' name='dateFormatTime' value='true'> output time as well as date
		  </span>
		</div>
	  </div>
	</li>

	<li class='cproforma cgroup clevel4 cfieldscomprising' id='ifieldcomprisingproforma' name='comprising'>
	  <button class='cmove cmove4'>&#x2195;</button>
	  <button class='cremove'>&#x274c;</button>
	  <div class='coptions cgroup'>
		<select class='cfieldoptionitem cinput4' name='item'>
		  <option value=''>choose&hellip;</option>
		  <option value='column' furtheroptions='cfieldcomprisingcolumn,cfieldcomprisingrowoffset'>column (letter or header):</option>
		  <option value='text' furtheroptions='cfieldcomprisingtext'>verbatim text:</option>
		</select>
		<input type='text' class='cfieldcomprisingcolumn cinitiallyhidden cfurtheroption cinput4' name='column' value='' placeholder='column letter/header'>
		<span class='cinitiallyhidden cfurtheroption cfieldcomprisingrowoffset'>row offset: 
		  <input type='text' class='cinput4 cshortinput cint' name='rowOffset' pattern='[0-9]*' value='' placeholder='row offset'>
		</span>
		<input type='text' class='cfieldcomprisingtext cinitiallyhidden cfurtheroption cinput4' name='text' value='' placeholder='text to include'>
	  </div>
	</li>
  </ul>
	
  <div>
	<a id='iasaveoptions'>save settings to file</a> <a class='chelp cinfo' href='help.php?a=hsavesettings'></a>
  </div>

  <div id='ihelp'>
     <div id='ihelpcontrols'>
       <a href='help.php' target='jcommahelp'>view in separate window</a> | <a href='#' id='ihelpclose'>close</a>
    </div>
    <div id='ihelpcontainer'>
      <iframe id='ihelpframe' src='help.php'></iframe>
    </div>
  </div>
</body>
</html>
