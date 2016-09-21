<?php

class jcomma {

  function __construct($path, $spec) {
    $this->path = $path;
    $this->spec = $spec;
    $this->currentrow = 0;
    $this->errors = array();
  }

  static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  
  static function oops($s) { throw new Exception($s); }  

  function columnletter($n) {
    if ($n < 26) { return self::$alphabet[$n]; }
    $l1 = (int)($n/26);
    $l2 = $n % 26;
    return self::$alphabet[$l1].self::$alphabet[$l2];
  }

  function columnnumber($l) {
    $l = strtoupper($l);
    if (isset($this->headings[$l])) { $l = $this->headings[$l]; /* convert to column letter */ }
    if (strlen($l) == 0) { self::oops('invalid column letter'); }
    if (strlen($l) == 1) {
      $n = strpos(self::$alphabet, $l);
      if ($n === FALSE) { self::oops('invalid column letter'); }
      return $n;
    }
    $n1 = strpos(self::$alphabet, $l[0]);
    $n1 = strpos(self::$alphabet, $l[1]);
    if ($n1 === FALSE || $n2 == FALSE) { self::oops('invalid column letter'); }
    return $n1 * 26 + $n2;
  }
  
  function checkint($field, $ge=NULL, $default=NULL){
    $v = $this->inspec($field, $default);
    if (gettype($v) != 'integer') {
      $this->errors[] = implode(', ', $field).' is not an integer';
      return $v;
    }
    if (! is_null($ge) && $v < $ge) {
      $this->errors[] = implode(', ', $field)." is less than {$ge}";
      return $v;
    }
    return $v;
  }

  function checkarray($field, $default=NULL) {
    $v = $this->inspec($field, $default);
    if (! is_array($v)) { $this->errors[] = implode(', ', $field).' is not an array'; }
    return $v;
  }

  function checkobject($field, $default=NULL) {
    $v = $this->inspec($field, $default);
    if (! is_object($v)) { $this->errors[] = implode(', ', $field).' is not an object'; }
    return $v;
  }

  function checkstring($field, $permitted=NULL, $emptyallowed=FALSE, $default=NULL) {
    $v = $this->inspec($field, $default);
    if (! is_string($v)) {
      $this->errors[] = implode(', ', $field).' is not a string';
    } else if (! is_null($permitted) && ! in_array($v, $permitted)) {
      $this->errors[] = implode(', ', $field).' is not a one of '.implode(', ', $permitted);
    } else if (! $emptyallowed && $field == '') {
      $this->errors[] = implode(', ', $field).' is empty';
    }
    return $v;
  }

  function checkcondition($field) {
    $conditions = array('empty', 'white', 'match', 'nomatch', 'eq', 'ne', 'ge', 'le');
    $condition = $this->checkstring($field, $conditions);
    array_pop($field);
    $field[] = 'value';
    switch($condition) {
    case 'match':
    case 'nomatch':
      $this->checkstring($field, NULL, TRUE);
      break;
    case 'ge':
    case 'le':
      $this->checkint($field);
      break;
    }
    return $condition;
  }
  
  function inspec($field, $default=NULL) {
    /* $field is an array of names and numbers where each name is an
       element and number an array index.  For example ("a", 5, "b")
       looks for and selects $this->spec->a[5]->b if it
       exists. Returns NULL if any of path missing, and sets an error
       if not optional (i.e. there is no default) */
    $o = $this->spec;
    for($i = 0; $i < count($field); $i++) {
      $f = $field[$i];
      $last = $i == count($field)-1;
      if (gettype($f) == 'integer') {
        if (! is_array($o)) {
          $this->errors[] = implode(', ', $field). " array expected";
          $o = array();
        }
        if (! isset($o[$f])) {
          if (is_null($default)) { $this->errors[] = implode(', ', $field). " is missing"; return NULL; }
          $o[$f] = $last ? $default : (gettype($field[$i+1] == 'integer') ? array() : new stdClass());
        }
        $o = $o[$f];
      } else {
        if (! is_object($o)) {
          $this->errors[] = implode(', ', $field). " object expected";
          $o = new stdClass();
        }
        if (! isset($o->$f)) {
          if (is_null($default)) { $this->errors[] = implode(', ', $field). " is missing"; }
          $o->$f = $last ? $default : (gettype($field[$i+1] == 'integer') ? array() : new stdClass());
        }
        $o = $o->$f;
      }
    }
    return $o;
  }
  
  function validate(){
    $this->checkstring(array('outputFormat'),
                       array('json','csv', 'html', 'xlsx', 'xml', 'qif'),
                       FALSE, 'jsonarray');
    $this->checkstring(array('outputTo'),
                       array('inline','attachment', 'string'),
                       FALSE, 'attachment');
    
    $this->checkint(array('headerRows'), 0, 0);
    $this->checkint(array('rowCount'), 1, 1);
    $this->checkstring(array('encoding'), NULL, FALSE, 'auto');

    $this->checkarray(array('ignoreRows'), array());
    for($i = 0; $i < count($this->spec->ignoreRows); $i++) {
      $this->checkstring(array('ignoreRows', $i, 'item'), array('column', 'field'));
      $this->checkstring(array('ignoreRows', $i, 'name'));
      $this->checkcondition(array('ignoreRows', $i, 'condition'));
    }

    $records = $this->checkarray(array('records'), array());
    if (count($records) == 0) {
      $this->errors[] = 'records array is empty - nothing would be produced';
    }

    for($ir = 0; $ir < count($this->spec->records); $ir++) {
      $unless = $this->checkarray(array('records', $ir, 'unless'), array());
      for ($iu = 0; $iu < count($unless); $iu++) {
        $this->checkstring(array('records', $ir, 'unless', $iu, 'field'));
        $this->checkcondition(array('records', $ir, 'unless', $iu, 'condition'));
      }
      
      $fields = $this->checkarray(array('records', $ir, 'fields'), array());
      for ($if = 0; $if < count($fields); $if++) {
        $this->checkstring(array('records', $ir, 'fields', $if, 'name'));        
        $options = $this->checkarray(array('records', $ir, 'fields', $if, 'options'), array());
        for($io = 0; $io < count($options); $io++) {
          $item = $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'item'),
                                     array('ignoreCurrency','replaceRegExp','replaceString','trim',
                                           'bookkeepersNegative',
                                           'omitIf','convertToNumber','convertToDate', 'convertToCustomDate',
                                           'errorOnValue'));
          switch($item) {
          case 'ignoreCurrency':
            $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'currencies'));
            break;
          case 'replaceRegExp':
          case 'replaceString':
            $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'matches'));
            $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'value'), NULL, TRUE, '');
            break;
          case 'omitIf':
            $this->checkcondition(array('records', $ir, 'fields', $if, 'options', $io, 'condition'));
            break;
          case 'errorOnValue':
            $this->checkcondition(array('records', $ir, 'fields', $if, 'options', $io, 'condition'));
            break;
          }
          
        }
        
        $comprising = $this->checkarray(array('records', $ir, 'fields', $if, 'comprising'), array());
        if (count($comprising) == 0) {
          $this->errors[] = implode(', ', array('records', $ir, 'fields', $if, 'comprising')).' is empty, nothing to do for this field';
        }
        for ($ic = 0; $ic < count($comprising); $ic++) {
          $item = $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'item'),
                                     array('column', 'text'));
          switch($item){
          case 'column':
            $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'column'));
            $this->checkint(array('records', $ir, 'fields', $if, 'comprising', $ic, 'rowOffset'), 0, 0);
            break;
          case 'text':
            $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'text'));          
            break;
          }          
        }        
      }
      
    }
        
    return $this->errors;
  }


  function meetscondition($condition, $vspec, $vcsv) {
    switch($condition) {
    case 'empty':
      return $vcsv == '';
    case 'white':
      return trim($vcsv) == '';
    case 'match':
    case 'nomatch':
      $matches = preg_match($vspec, $vcsv);
      if ($matches === FALSE) { oops("incorrect regexp '{$vspec}'"); }
      return $matches == ($condition == 'match' ? 1 : 0);
    case 'eq':
      if (gettype($vcsv) == 'integer' || gettype($vcsv) == 'float') { $vspec = (float)$vspec; }
      return $vspec == $vcsv;
    case 'ne':
      if (gettype($vcsv) == 'integer' || gettype($vcsv) == 'float') { $vspec = (float)$vspec; }
      return $vspec != $vcsv;
    case 'ge':
      return float($vspec) >= float($vcsv);
    case 'le':
      return float($vspec) <= float($vcsv);
    default:
      return FALSE;
    }
  }
  
  function readrows($n, $exact=FALSE){
    $rows = array();
    for($i = 0; $i < $n; $i++) {
      $row = fgetcsv($this->fd);
      $this->currentrow++;
      if ($row === FALSE) { return FALSE; }
      if (! $exact) {
        /* see if we should ignore this row per condition specified */
        foreach($this->spec->ignoreRows as $ignoreRow) {
          if ($ignoreRow->item != 'column') { continue; }
          $nc = $this->columnnumber($ignoreRow->name);
          if (isset($row[$nc])) {
            if ($this->meetscondition($ignoreRow->condition,
                                      isset($ignoreRow->value) ? $ignoreRow->value : '', $row[$nc]))
            {
              $i--; /* still get the same number of rows eventually even though we ignore this one */
              continue 2;
            }
          }
        }
      }
      $encoding = $this->spec->encoding == 'auto' ? mb_detect_encoding(implode(' ', $row)) : $this->spec->encoding;
      if ($encoding != 'UTF-8' && $encoding != 'ASCII') {
        for($ie = 0; $ie < count($row); $ie++) { $row[$ie] = iconv($encoding, 'UTF-8', $row[$ie]); }
      }
      $rows[] = $row;
    }
    return $rows;
  }
  
  function dotted($o, $s) {
    /* selects $o->$a->$b->... where $s is something like "a.b...", returning NULL if anything on the 
       path does not exist */
    foreach(explode('.', $s) as $f) {
      if (! is_object($o) || ! isset($o->$f)) { return NULL; }
      $o = $o->$f;
    }
    return $o;
  }

  function convert() {
    $this->fd = fopen($this->path, 'r');
    if ($this->fd === FALSE) { self::oops("cannot open csv file"); }

    $this->headings = NULL;
    if ($this->spec->headerRows > 0) {
      $rows = $this->readrows($this->spec->headerRows, TRUE /* exactly that number, don't ignore any rows */);
      if (empty($rows)) { self::oops('nothing useful in file except possibly headers'); }
      for ($i = 0; $i < count($rows[$this->spec->headerRows-1]); $i++) {
        $this->headings[strtoupper($rows[$this->spec->headerRows-1][$i])] = $this->columnletter($i);
      }
    }

    $output = array();
    
    for(;;) {
      $rows = $this->readrows($this->spec->rowCount);
      if (empty($rows)) { break; }

      /* produce each record required from this group of fields */
      foreach($this->spec->records as $record) {

        $outputrecord = new stdClass();
          
        foreach ($record->fields as $field) {

          /* calculate outputvalue for field according to 'comprising' */
          $outputvalue = '';
          $outputtype = 'string';
          foreach($field->comprising as $comprising) {
            switch($comprising->item) {
            case 'column':
              $rowOffset = isset($comprising->rowOffset) ? $comprising->rowOffset : 0;
              if (isset($rows[$rowOffset][$this->columnnumber($comprising->column)])) {
                $outputvalue .= $rows[$rowOffset][$this->columnnumber($comprising->column)];
              }
              break;
            case 'text':
              $outputvalue .= $comprising->text;
              break;
            }
          }

          /* apply options */
          foreach($field->options as $option) {
            if (! isset($option->item)) { continue; }
            switch($option->item){
            case 'ignoreCurrency':
              if (empty($option->currencies)) { break; }
              for($i = 0; $i < mb_strlen($option->currencies); $i++) {
                $currency = mb_substr($option->currencies, $i, 1);
                $outputvalue = str_replace($currency, '', $outputvalue);
                /* everything is utf8, so should be safe to use str_replace */
              }
              break;
            case 'replaceRegExp':
              if (empty($option->matches)) { break; }
              $outputvalue = preg_replace($option->matches, $option->output, $outputvalue);
              if (is_null($outputvalue)) { oops("incorrect regexp '{$option->matches}'"); }
              break;
            case 'replaceString':
              if (empty($option->matches)) { break; }
              $outputvalue = str_replace($option->matches, $option->output, $outputvalue);
              break;
            case 'trim':
              $outputvalue = trim($outputvalue);
              break;
            case 'bookkeepersNegative':
              $outputvalue = preg_replace('~^\\([0-9\\.]*)\\)$~', '-$1', $outputvalue);
              $outputvalue = preg_replace('~^([0-9\\.]*)-$~', '-$1', $outputvalue);
              break;
            case 'omitIf':
                if (empty($option->condition)) { break; }
                $optionvalue = ! isset($option->value) ? '' : $option->value;
                if ($this->meetscondition($option->condition, $optionvalue, $outputvalue)) {
                  continue 3;
                }
                break;
            case 'errorOnValue':
                if (empty($option->condition)) { break; }
                $optionvalue = ! isset($option->value) ? '' : $option->value;
                if ($this->meetscondition($option->condition, $optionvalue, $outputvalue)) {
                  $this->oops("at row {$this->currentrow}, {$outputvalue}, failed errorOnValue check)");
                }
                break;
            case 'convertToNumber':
              if (! is_numeric($outputvalue)) {
                if (! empty($option->errorOnType)) {
                  $this->oops("at row {$this->currentrow}, {$outputvalue} is not numeric (failed errorOnType check)");
                }
                $outputvalue = 0;
              } else {
                $outputvalue = (float)$outputvalue;
              }
              if (! empty($option->negate)) { $outputvalue = - $outputvalue; }
              break;
            case 'convertToDate':
            case 'convertToCustomDate':
              $date = empty($option->dateFormatUS) ? str_replace('/', '-', $outputvalue) : $outputvalue;
              $time = strtotime($date);
              if ($time === FALSE) {
                if (! empty($option->errorOnType)) {
                  $this->oops("at row {$this->currentrow}, {$outputvalue} does not look like a date/time (failed errorOnType check)");
                }
                $outputvalue = '';
              } else {
                $outputvalue = date($option->item == 'convertToDate' || empty($option->dateFormatStyle) ?
                                    'c' /* ISO */ : $option->dateFormatStyle,
                                    $time);
                if ($option->item == 'convertToDate' && empty($option->dateFormatTime)) {
                  $outputvalue = substr($outputvalue, 0, 10); /* just leave date part */
                }
              }
              $outputtype = 'date';
              break;
            }
          }

          /* assign to result, taking account of dotted fields */
          $dotteds = explode('.', $field->name);
          $o = $outputrecord;
          for($i = 0; $i < count($dotteds)-1 /* sic */; $i++) {
            if (! isset($o->{$dotteds[$i]})) { $o->{$dotteds[$i]} = new stdClass(); }
            $o = $o->{$dotteds[$i]};
          }
          $o->{$dotteds[count($dotteds)-1]} = $outputvalue;
          if (! isset($this->outputtypes[$field->name])) { $this->outputtypes[$field->name] = $outputtype; }
        }
          
        /* abandon the record if any field condition is met */
        foreach($record->unless as $unless) {
          $unlessvalue = ! isset($unless->value) ? '' : $unless->value;
          if ($this->meetscondition($unless->condition, $unlessvalue,
                                    $this->dotted($outputrecord, $unless->name))) { continue 2; }
        }
        
        /* abandon the record if any ignore row condition based on field is met */
        foreach($this->spec->ignoreRows as $ignoreRow) {
          if ($ignoreRow->item != 'field') { continue; }
          $ignorevalue = ! isset($ignoreRow->value) ? '' : $ignoreRow->value;
          if ($this->meetscondition($ignoreRow->condition, $ignorevalue,
                                    $this->dotted($outputrecord, $ignoreRow->name))) { continue 2; }
        }
        
        /* if everything is OK, finally, save the new record */
        $output[] = $outputrecord;
      }
    }
    
    fclose($this->fd);

    return $output;
  }

  /* ================================================== */
  /* now the output functions... */
  
  function output($output, $inputfilename, $cl=FALSE){
    $mimetypes = array('json'=>'application/json',
                       'csv'=>'application/csv',
                       'html'=>'text/html',
                       'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'xml'=>'text/xml',
                       'qif'=>'text/plain',
    );
    $this->cl = $cl;
    $this->elementname = empty($this->spec->outputName) ?
                       preg_replace('~[^a-z0-9_]~i', '', str_replace('.csv','',$inputfilename)) :
                       $this->spec->outputName;
    if (! $this->cl && $this->spec->outputTo != 'string') {
      header("Content-type: {$mimetypes[$this->spec->outputFormat]}");
      header($this->spec->outputTo == 'inline' && 
             $this->spec->outputFormat != 'csv' && $this->spec->outputFormat != 'xlsx' ?
               'Content-disposition: inline' :
               'Content-disposition: attachment; filename="'.str_replace('.csv', ".{$this->spec->outputFormat}",
                                                                         $inputfilename).'"');
      header('Content-Transfer-Encoding: binary');
      ob_end_flush();
    }

    switch($this->spec->outputFormat){
    case 'json': $s = $this->outputjson($output); break;
    case 'csv': $s = $this->outputcsv($output); break;
    case 'html': $s = $this->outputhtml($output); break;
    case 'xlsx': $s = $this->outputxlsx($output); break;
    case 'xml': $s = $this->outputxml($output); break;
    case 'qif': $s = $this->outputqif($output); break;
    }

    if ($this->spec->outputTo == 'string') { return $s; } else { echo $s; }
  }

  function outputjson($output){
    if (empty($this->spec->outputBulkElastic)) {
      $s = ! empty($this->spec->outputStyle) && $this->spec->outputStyle == 'pretty' ?
         json_encode($output, JSON_PRETTY_PRINT) : json_encode($output);
    } else {
      $s = '';
      foreach($output as $record) {
        $s .= json_encode(array('create'=>array('_type'=>$this->elementname)))."\n".json_encode($record)."\n";
      }
    }
    return $s;
  }

  function outputcsv($output) {
    $encoding = empty($this->spec->outputEncoding) ? NULL : $this->spec->outputEncoding;
    $s = ! empty($this->spec->outputHeaderRow) ? $this->csv_keys($output[0], '', array(), $encoding) : '';
    foreach($output as $record) { $s .= $this->csv_values($record, '', $encoding); }
    return $s;
  }

  function csv_value($v, $p, $encoding) {
    if (! empty($encoding)) { $v = iconv('UTF-8', $encoding, $v); }
    if (strpos($v, '"') !== FALSE || strpos($v, ',') !== FALSE) {
      return $p.'"'.str_replace('"', '""', $v).'"';
    } else {
      return $p.$v;
    }
  } 
    
  function csv_values($data, $p, $encoding){
    $s = '';
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $s .= $this->csv_values($v, $p, $encoding);
      } else {
        $s .= $this->csv_value($v, $p, $encoding);
      }
      $p = ',';
    }
    return $s."\n";
  }

  function csv_keys($data, $p, $ks, $encoding){
    $s = '';
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $s .= $this->csv_keys($v, $p, array_merge($ks, $k), $encoding);
      } else if (! empty($ks)) {
        $s .= $this->csv_value(implode('.', array_merge($ks, $k)), $p, $encoding);
      } else {
        $s .= $this->csv_value($k, $p, $encoding);
      }
      $p = ',';
    }
    return $s."\n";
  }
  
  function outputxlsx($output) {
    include_once('PHP_XLSXWriter/xlsxwriter.class.php');
    $x = new XLSXWriter();
    if (! empty($this->spec->outputHeaderRow) && ! empty($output)) {
      $x->writeSheetHeader('Sheet1', $this->xlsx_flattenkeys($output[0], array()));
    }
    foreach($output as $row) {
      $x->writeSheetRow('Sheet1', $this->xlsx_flatten($row));
    }
    $fn = tempnam ('/tmp', 'jcomma-xlsx-');
    $x->writeToFile($fn);
    return file_get_contents($fn);
  }

  function xlsx_flatten($data){
    $a = array();
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $a = array_merge($a, $this->xlsx_flatten($v));
      } else {
        $a[] = $v;
      }
    }
    return $a;
  }

  function xlsx_flattenkeys($data, $ks){
    $a = array();
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $a = array_merge($a, $this->xlsx_flattenkeys($v, array_merge($ks, array($k))));
      } else if (! empty($ks)) {
        $k = implode('.', array_merge($ks, $k));
        $type = gettype($v);
        if ($type == 'string' && isset($this->outputtypes[$k])) { $type = $this->outputtypes[$k]; }        
        $a[$k] = $v;
      } else {
        $type = gettype($v);
        if ($type == 'string' && isset($this->outputtypes[$k])) { $type = $this->outputtypes[$k]; }
        $a[$k] = $type;
      }
    }
    return $a;
  }
  
  function outputhtml($output) {
    $s = '<'.'!'.'doctype html'.'>'."\n";
    $s .= <<<EOD
<html>
<head>
<meta charset='UTF-8'>
<style>
body { font-family: Arial, Helvetica, sans-serif; }
table { border-collapse: collapse; }
td { border: 1px solid black; padding: 2px; min-height: 18px; }
</style>
</head>
<body>
<table>

EOD;
    if (! empty($this->spec->outputHeaderRow)) {
      $s .= "<thead><tr>\n";
      $s .= $this->html_keys($output[0], array());
      $s .= "</tr></thead>\n";
    }
    $s .= "<tbody>\n";
    foreach($output as $record) {
      $s .= "<tr>\n".$this->html_values($record)."</tr>\n";
    }
    $s .= <<<EOD
</tbody>
</table>
</body>
</html>

EOD;
    return $s;
  }

  function html_value($v) {
    return '<td>'.htmlspecialchars($v).'</td>';
  }

  function html_values($data){
    $s = '';
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $s .= $this->html_values($v);
      } else {
        $s .= $this->html_value($v);
      }
    }
    return $s;
  }

  function html_keys($data, $ks){
    $s = '';
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v)) {
        $s .= $this->html_keys($v, array_merge($ks, $k));
      } else if (! empty($ks)) {
        $s .= $this->html_value(implode('.', array_merge($ks, $k)));
      } else {
        $s .= $this->html_value($k);
      }
    }
    return $s;
  }

  function outputxml($output) {
    $s = '<'.'?'.'xml version="1.0" encoding="UTF-8" standalone="yes" '.'?'.'>'."\n";
    $s .= "<{$this->elementname}s>\n";
    foreach($output as $record) {
      $s .= empty($this->spec->outputXMLElements) ? $this->xmlify_attributes($record) : $this->xmlify_elements($record);
    }
    return "{$s}</{$this->elementname}s>\n";
  }

  function xmlify_attributes($record){
    $s = "<{$this->elementname}";
    $subordinates = array();
    if (is_object($record)) { $record = (array)$record; }
    foreach($record as $k=>$v) {
      if (is_object($v)) { $subordinates[$k] = $v; }
      else { $s .= " {$k}='".htmlspecialchars($v)."'"; }
    }
    $s .= '>';
    foreach ($subordinates as $k=>$v) { $s .= $this->xmlify_attributes($v, $k); }
    return $s."</{$this->elementname}>\n";
  }

  function xmlify_elements($record){
    $s = "<{$this->elementname}>\n";
    if (is_object($record)) { $record = (array)$record; }
    foreach($record as $k=>$v) {
      if (is_object($v)) { $s .= $this->xmlify_elements($v, $k); }
      else { $s .= "  <{$k}>".htmlspecialchars($v)."</{$k}>\n"; }
    }
    return $s."</{$this->elementname}>\n";
  }

  function outputqif($output) {
    $s = '!Type:'.(empty($this->spec->outputQIFType) ? 'Bank' : $this->spec->outputQIFType)."\n";
    foreach($output as $record) {
      foreach($record as $k=>$v) {
        switch($k) {
        case 'T': 
        case '$':
          $v = sprintf('%0.2f', $v);
          break;
        }
        $s .= "{$k}{$v}\n";
      }
      $s .= "^\n";
    }
    return $s;
  }
  
}
