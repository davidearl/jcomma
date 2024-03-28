<?php

namespace DavidEarl\JComma;

define ("CURRENT_VERSION", 7);

class JComma {

  private $fd;
  private $path;
  private $recipe;
  private $currentrow;
  private $errors;
  private $encoding;
  private $headings;
  private $outputtypes;
  private $cl;
  private $elementname;
  
  function __construct($path_or_filedescriptor, $recipe) {
    if (is_string($path_or_filedescriptor)) {
      $this->path = $path_or_filedescriptor;
    } else {
      $this->fd = $path_or_filedescriptor;
    }
    $this->recipe = $recipe;
    $this->currentrow = 0;
    $this->errors = [];
  }

  static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  
  static function oops($s) {
    error_log(print_r(debug_backtrace(),1));
    throw new \Exception($s);
  }  

  function columnletter($n) {
    if ($n < 26) { return self::$alphabet[$n]; }
    $l1 = (int)($n/26)-1;
    $l2 = $n % 26;
    return self::$alphabet[$l1].self::$alphabet[$l2];
  }

  function columnnumber($l) {
    $ll = strtoupper($l);
    if (isset($this->headings[$ll])) { $ll = $this->headings[$ll]; /* convert to column letter */ }
    if (strlen($ll) == 0) { self::oops("invalid column identifier: {$l}"); }
    if (strlen($ll) == 1) {
      $n = strpos(self::$alphabet, $ll);
      if ($n === FALSE) { self::oops("invalid column identifier: {$l}"); }
      return $n;
    }
    if (strlen($ll) != 2) { self::oops("invalid column identifier: {$l}"); }
    $n1 = strpos(self::$alphabet, $ll[0]);
    $n2 = strpos(self::$alphabet, $ll[1]);
    if ($n1 === FALSE || $n2 === FALSE) { self::oops("invalid column identifier: {$l}"); }
    $n = ($n1+1) * 26 + $n2;
    return $n;
  }

  function describefield($field, $text=NULL, $value=NULL){
    $s = '';
    $p = '';
    foreach ($field as $f) {
      if (gettype($f) == 'integer') { $s .= "[{$f}]"; }
      else { $s .= "{$p}{$f}"; }
      $p = '.';
    }
    if (! is_null($value)) { $s .= " ({$value})"; }
    if (! is_null($text)) { $s .= " {$text}"; }
    return $s;
  }
  
  function checkint($field, $ge=NULL, $default=NULL){
    $v = $this->inrecipe($field, $default);
    if (gettype($v) != 'integer') {
      $this->errors[] = $this->describefield($field, 'is not an integer', $v);
      return $v;
    }
    if (! is_null($ge) && $v < $ge) {
      $this->errors[] = $this->describefield($field, "is less than {$ge}", $v);
      return $v;
    }
    return $v;
  }

  function checkfloat($field, $default=NULL){
    $v = $this->inrecipe($field, $default);
    if (! is_numeric($v)) {
      $this->errors[] = $this->describefield($field, 'is not a number', $v);
      return $v;
    }
  }

  function checkdate($field, $default=NULL){
    $v = $this->inrecipe($field, $default);
    if (strpos($v, '/') !== FALSE) {
      $this->errors[] = $this->describefield($field, ' - ambiguous date format (use YYYY-MM-DD)', $v);
      return $v;
    }
    if (strtotime($v) === FALSE) {
      $this->errors[] = $this->describefield($field, 'is not a date', $v);
      return $v;
    }
  }

  function checkarray($field, $default=NULL) {
    $v = $this->inrecipe($field, $default);
    if (! is_array($v)) { $this->errors[] = $this->describefield($field, 'is not an array'); }
    return $v;
  }

  function checkobject($field, $default=NULL) {
    $v = $this->inrecipe($field, $default);
    if (! is_object($v)) { $this->errors[] = $this->describefield($field, 'is not an object'); }
    return $v;
  }

  function checkstring($field, $permitted=NULL, $emptyallowed=FALSE, $default=NULL) {
    $v = $this->inrecipe($field, $default);
    if (! is_string($v)) {
      $this->errors[] = ' is not a string';
    } else if (! is_null($permitted) && ! in_array($v, $permitted)) {
      $this->errors[] = $this->describefield($field, 'is not one of '.implode(', ', $permitted), $v);
    } else if (! $emptyallowed && $field == '') {
      $this->errors[] = $this->describefield($field, 'is empty');
    }
    return $v;
  }

  function checkcondition($field) {
    $conditions = ['empty', 'white', 'match', 'nomatch', 'eq', 'ne', 'ge', 'le', 'before', 'after', 'eqprev', 'neprev'];
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
      $this->checkfloat($field);
      break;
    case 'before':
    case 'after':
      $this->checkdate($field);
      break;
    }
    return $condition;
  }
  
  function inrecipe($field, $default=NULL) {
    /* $field is an array of names and numbers where each name is an
       element and number an array index.  For example ("a", 5, "b")
       looks for and selects $this->recipe->a[5]->b if it
       exists. Returns NULL if any of path missing, and sets an error
       if not optional (i.e. there is no default) */
    $o = $this->recipe;
    for($i = 0; $i < count($field); $i++) {
      $f = $field[$i];
      $last = $i == count($field)-1;
      if (gettype($f) == 'integer') {
        if (! is_array($o)) {
          $this->errors[] = $this->describefield($field, '- array expected');
          $o = [];
        }
        if (! isset($o[$f])) {
          if (is_null($default)) { $this->describefield($field, 'is missing'); return NULL; }
          $o[$f] = $last ? $default : (gettype($field[$i+1] == 'integer') ? [] : new \stdClass());
        }
        $o = $o[$f];
      } else {
        if (! is_object($o)) {
          $this->errors[] = $this->describefield($field, '- object expected');
          $o = new \stdClass();
        }
        if (! isset($o->$f)) {
          if (is_null($default)) { $this->errors[] = $this->describefield($field, 'is missing'); }
          $o->$f = $last ? $default : (gettype($field[$i+1] == 'integer') ? [] : new \stdClass());
        }
        $o = $o->$f;
      }
    }
    return $o;
  }
  
  function validate(){
    $this->checkint(['recipeVersion'], 1, 1);
    if ($this->recipe->recipeVersion > CURRENT_VERSION) {
      $this->oops("The server version ".CURRENT_VERSION." predates the recipe version ({$this->recipe->recipeVersion}) supplied");
    }
    $this->checkstring(['outputFormat'],
                       ['json','csv', 'html', 'xlsx', 'xml', 'qif'],
                       FALSE, 'jsonarray');
    $this->checkstring(['outputTo'],
                       ['inline','attachment', 'string'],
                       FALSE, 'attachment');
    
    $this->checkint(['headerRows'], 0, 0);
    $this->checkint(['rowCount'], 1, 1);
    $this->checkstring(['encoding'], NULL, FALSE, 'auto');

    $v = $this->checkstring(['delimiterChar'], NULL, TRUE, ',');
    if (strlen($v) > 1) {
      $this->errors[] = $this->describefield(['delimiterChar'],
                                             ' is too long (only 1 character allowed)');
    }
    $v = $this->checkstring(['enclosureChar'], NULL, TRUE, '"');
    if (strlen($v) > 1) {
      $this->errors[] = $this->describefield(['enclosureChar'],
                                             ' is too long (only 1 character allowed)');
    }
    
    $this->checkarray(['ignoreRows'], []);
    for($i = 0; $i < count($this->recipe->ignoreRows); $i++) {
      $this->checkstring(['ignoreRows', $i, 'item'], ['column', 'field']);
      $this->checkstring(['ignoreRows', $i, 'name']);
      $this->checkcondition(['ignoreRows', $i, 'condition']);
    }

    $this->checkarray(['combineRows'], []);
    for($i = 0; $i < count($this->recipe->combineRows); $i++) {
      $this->checkstring(['combineRows', $i, 'name']);
      $this->checkcondition(['combineRows', $i, 'condition']);
    }

    $records = $this->checkarray(['records'], []);
    if (count($records) == 0) {
      $this->errors[] = 'records array is empty - nothing would be produced';
    }

    for($ir = 0; $ir < count($this->recipe->records); $ir++) {
      $unless = $this->checkarray(['records', $ir, 'unless'], []);
      for ($iu = 0; $iu < count($unless); $iu++) {
        $this->checkstring(['records', $ir, 'unless', $iu, 'field']);
        $this->checkcondition(['records', $ir, 'unless', $iu, 'condition']);
      }
      
      $fields = $this->checkarray(['records', $ir, 'fields'], []);
      for ($if = 0; $if < count($fields); $if++) {
        $this->checkstring(['records', $ir, 'fields', $if, 'name']);        
        $options = $this->checkarray(['records', $ir, 'fields', $if, 'options'], []);
        for($io = 0; $io < count($options); $io++) {
          $test = $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'test'],
                                     ['value','field','column'], TRUE, 'value');
          switch ($test) {
          case 'field':
            $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'field']);
            break;
          case 'column':
            $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'column']);
            break;
          }

          $item = $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'item'],
                                     ['ignoreCurrency','replaceRegExp','replaceString','trim',
                                      'bookkeepersNegative','skipIf','skipUnless','omitIf', 'carryOverIf',
                                      'convertToNumber','convertToNumberSum',
                                      'convertToDate', 'convertToCustomDate',
                                      'errorOnValue']);
          switch($item) {
          case 'ignoreCurrency':
            $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'currencies']);
            break;
          case 'replaceRegExp':
          case 'replaceString':
            $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'matches']);
            $this->checkstring(['records', $ir, 'fields', $if, 'options', $io, 'value'], NULL, TRUE, '');
            break;
          case 'skipIf':
          case 'skipUnless':
          case 'omitIf':
          case 'carryOverIf':
            $this->checkcondition(['records', $ir, 'fields', $if, 'options', $io, 'condition']);
            break;
          case 'errorOnValue':
            $this->checkcondition(['records', $ir, 'fields', $if, 'options', $io, 'condition']);
            break;
          }
          
        }
        
        $comprising = $this->checkarray(['records', $ir, 'fields', $if, 'comprising'], []);
        if (count($comprising) == 0) {
          $this->errors[] = $this->describefield(['records', $ir, 'fields', $if, 'comprising'], 'is empty, nothing to do for this field');
        }
        for ($ic = 0; $ic < count($comprising); $ic++) {
          $item = $this->checkstring(['records', $ir, 'fields', $if, 'comprising', $ic, 'item'],
                                     ['column', 'text', 'field', 'previouscolumn', 'previousfield']);
          switch($item){
          case 'column':
          case 'previouscolumn':
            $this->checkstring(['records', $ir, 'fields', $if, 'comprising', $ic, 'column']);
            $this->checkint(['records', $ir, 'fields', $if, 'comprising', $ic, 'rowOffset'], 0, 0);
            break;
          case 'text':
            $this->checkstring(['records', $ir, 'fields', $if, 'comprising', $ic, 'text']); 
            break;
          case 'field':
          case 'previousfield':
            $this->checkstring(['records', $ir, 'fields', $if, 'comprising', $ic, 'field']); 
            break;
          }          
        }        
      }
      
    }
        
    return $this->errors;
  }


  function meetscondition($condition, $vrecipe, $vcsv, $previousrows) {
    switch($condition) {
    case 'empty':
      return $vcsv == '';
    case 'white':
      return trim($vcsv) == '';
    case 'match':
    case 'nomatch':
      $matches = preg_match($vrecipe, $vcsv);
      if ($matches === FALSE) { self::oops("incorrect regexp '{$vrecipe}'"); }
      return $matches == ($condition == 'match' ? 1 : 0);
    case 'eq':
      if (gettype($vcsv) == 'integer' || gettype($vcsv) == 'float') { $vrecipe = (float)$vrecipe; }
      return $vrecipe == $vcsv;
    case 'ne':
      if (gettype($vcsv) == 'integer' || gettype($vcsv) == 'float') { $vrecipe = (float)$vrecipe; }
      return $vrecipe != $vcsv;
    case 'ge':
      return (float)$vcsv >= (float)$vrecipe;
    case 'le':
      return (float)$vcsv <= (float)$vrecipe;
    case 'before':
      if (strpos($vcsv, '/') !== FALSE) { self::oops("{$vscv} is an ambiguous date format"); }
      return strtotime($vcsv) < strtotime($vrecipe);
    case 'after':
      if (strpos($vcsv, '/') !== FALSE) { self::oops("{$vscv} is an ambiguous date format"); }
      return strtotime($vcsv) > strtotime($vrecipe);
    case 'eqprev':
    case 'neprev':
      if (! isset($previousrows[0 /* allow rowOffset here? */][$this->columnnumber($vrecipe)])) { return FALSE; }
      $vrecipe = $previousrows[0 /* allow rowOffset here? */][$this->columnnumber($vrecipe)];
      if (gettype($vcsv) == 'integer' || gettype($vcsv) == 'float') { $vrecipe = (float)$vrecipe; }
      return $condition == 'eqprev' ? $vrecipe == $vcsv : $vrecipe != $vcsv;
    default:
      return FALSE;
    }
  }
  
  function comparevalue($option, $rows, $previousrows, $outputvalue, $outputrecord, $previousoutput) {
    $test = empty($option->test) ? 'value' : $option->test;
    switch($test) {
    case 'column':
      if (! isset($rows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)])) {
        return '';
      }
      return $rows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)];
    case 'previouscolumn':
      if (! isset($previousrows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)])) { return ''; }
      return $previousrows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)];
    case 'field':
      if (! isset($outputrecord->{$option->field})) {
        $this->oops("at row {$this->currentrow}, {$option->field} is not a previous field in the same record");
      }
      return $outputrecord->{$option->field};
    case 'previousfield':
      if (! isset($outputrecord->{$option->field})) {
        $this->oops("at row {$this->currentrow}, {$option->field} is not a previous field in the same record");
      }
      return $outputrecord->{$option->field};
    case 'value':
      return $outputvalue;
    }
  }

  function asutf8($row /* array of cells (strings) */) {
    if (! isset($this->encoding)) {        
      if (empty($this->recipe->encoding)) { $this->recipe->encoding = 'auto'; }
      if ($this->recipe->encoding != 'auto') {
        $this->encoding = $this->recipe->encoding;
      } else {
        $topbitset = FALSE;
        $s = implode(' ', $row);
        for($i = 0; $i < strlen($s); $i++) {
          if (ord($s[$i]) & 0x80) {
            $topbitset = TRUE;
            break;
          }
        }
        if ($topbitset) {
          $encoding = mb_detect_encoding($s, ['UTF-8', 'ASCII', 
            'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 
            'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 
            'Windows-1251', 'Windows-1252', 'Windows-1254']);
          if (empty($encoding)) { self::oops("unable to detect CSV's encoding - choose one explicitly"); }
          if ($encoding != 'UTF-8' && $encoding != 'ASCII') { $this->encoding = $encoding; }
        }
      }
    }
    if (isset($this->encoding) && $this->encoding != 'UTF-8' && $this->encoding != 'ASCII') {
      for ($i = 0; $i < count($row); $i++) { $row[$i] = iconv($this->encoding, 'UTF-8', $row[$i]); }
    }
    return $row;
  }
  
  function readrows($rowCount, $previousrows, $exact=FALSE){
    $rows = [];
    for($i = 0; $i < $rowCount; $i++) {
      $row = fgetcsv($this->fd, NULL,
                     $this->recipe->delimiterChar, $this->recipe->enclosureChar);
      $this->currentrow++;
      if ($row === FALSE) { return FALSE; }
      if (! $exact) {
        /* see if we should combine next row per condition specified: this requires looking ahead */
        if (! empty($this->recipe->combineRows)) {
          for(;;) {
            $fp = ftell($this->fd); /* for when we don't combine */
            $nextRow = fgetcsv($this->fd, NULL,
                               $this->recipe->delimiterChar, $this->recipe->enclosureChar);
            if ($nextRow === FALSE) { break; }
            foreach($this->recipe->combineRows as $combineRow) {
              $nc = $this->columnnumber($combineRow->name);
              $v = isset($nextRow[$nc]) ? $nextRow[$nc] : '';
              if ($this->meetscondition($combineRow->condition, isset($combineRow->value) ? $combineRow->value : '',
                                        $v, NULL))
              {
                $rows[] = $this->asutf8($row);
                $row = $nextRow;
                continue 2; /* to try another combine */
              }
            }
            break;
          }
          fseek($this->fd, $fp);
        }

        /* see if we should ignore this row per condition specified */
        foreach($this->recipe->ignoreRows as $ignoreRow) {
          if ($ignoreRow->item != 'column') { continue; }
          $nc = $this->columnnumber($ignoreRow->name);
          $v = isset($row[$nc]) ? $row[$nc] : '';
          if ($this->meetscondition($ignoreRow->condition, $this->valueOf($ignoreRow), $v, $previousrows)) {
            $i--; /* still get the same number of rows eventually even though we ignore this one */
            continue 2;
          }
        }
      }
      $rows[] = $this->asutf8($row);
    }
    return $rows;
  }
  
  function varpath($r, $name, $value=NULL) {
    /* retrieves and, if value not null, sets the value in r according to path, which should 
       be something like 'a[3].b' or 'a[3].b[4]', or more likely just 'a' */
    $o = $r;
    $parts = preg_split('~[\\[\\.]~', $name);
    if ($parts[0] == '') { $this->oops("missing field name in '{$name}'"); }
    if (! isset($o->{$parts[0]})) { $o->{$parts[0]} = NULL; /* for now; this should be a name */ }
    $o =& $o->{$parts[0]};
    for($i = 1; $i < count($parts); $i++) {
      if (preg_match('~^\\s*([0-9]+)\\s*\\]\\s*$~', $parts[$i], $m)) {
        $index = (int)($m[1]);
        if (is_null($o)) {
          $o = [];
        } else if (isset($o->{$parts[$i]}) && is_object($o)) {
          $this->oops("for field name '{$name}' array/object structure is inconsistent with an earlier one");
        }
        if (! isset($o[$index])) { $o[$index] = NULL; }
        $o =& $o[$index];
      } else if (strpos(']', $parts[$i]) !== FALSE) {
        $this->oops("for field name '{$name}' array subscript needs to be preceded by a name");
      } else {
        if (is_null($o)) {
          $o = new \stdClass();
        } else if (isset($o->{$parts[$i]}) && is_array($o->{$parts[$i]})) {
          $this->oops("for field name '{$name}' array/object structure is inconsistent with an earlier one");
        }
        if (! isset($o->{$parts[$i]})) { $o->{$parts[$i]} = NULL; }
        $o =& $o->{$parts[$i]};
      }
    }
    if (! is_null($value)) { $o = $value; }
    return $o;
  }

  function valueOf($test){
    switch($test->condition){
    default:
      return ! isset($test->value) ? '' : $test->value;
    case 'eqprev':
    case 'neprev':
      return ! isset($test->prevcolumn) ? '' : $test->prevcolumn;
    }
  }
  
  function convert() {
    if (! empty($this->path)) { $this->fd = fopen($this->path, 'r'); }
    if ($this->fd === FALSE) { self::oops("cannot open csv file"); }

    $this->headings = NULL;
    if ($this->recipe->headerRows > 0) {
      $rows = $this->readrows($this->recipe->headerRows, NULL, TRUE /* exactly that number, don't ignore any rows */);
      if (empty($rows)) { self::oops('nothing useful in file except possibly headers'); }
      /* there may be a byte order mark, and fgetcsv seems to ignore this */
      if (count($rows[0]) > 0 && substr($rows[0][0], 0, 3) == "\xef\xbb\xbf" /* BOM */) {
        $rows[0][0] = substr($rows[0][0], 3);
        /* and it may also have left quotes because of this... */
        if (strlen($rows[0][0]) >=2 && $rows[0][0][0] == '"') {
          $rows[0][0] = str_replace('""', '"', substr($rows[0][0], 1, strlen($rows[0][0])-2));
        }
      }
      for ($i = 0; $i < count($rows[$this->recipe->headerRows-1]); $i++) {
        $this->headings[strtoupper($rows[$this->recipe->headerRows-1][$i])] = $this->columnletter($i);
      }
    }

    $output = [];

    $rows = NULL;
    $outputgroup = [];
    
    for(;;) {
      $previousrows = $rows;
      $previousrecords = $outputgroup;
      $rows = $this->readrows($this->recipe->rowCount, $previousrows);
      if (empty($rows)) { break; }

      /* produce each record required from this group of fields */
      foreach($this->recipe->records as $idxRecord => $record) {

        $outputrecord = new \stdClass();
        $excludefields = [];
        
        foreach ($record->fields as $field) {

          /* calculate outputvalue for field according to 'comprising' */
          $outputvalue = '';
          $outputtype = 'string';
          if (! empty($field->exclude)) { $excludefields[] = $field->name; }

          foreach($field->comprising as $comprising) {
            switch($comprising->item) {
            case 'column':
              $rowOffset = isset($comprising->rowOffset) ? $comprising->rowOffset : 0;
              $inputvalue = isset($rows[$rowOffset][$this->columnnumber($comprising->column)]) ?
                          $rows[$rowOffset][$this->columnnumber($comprising->column)] :
                          '';
              break;
            case 'previouscolumn':
              $rowOffset = isset($comprising->rowOffset) ? $comprising->rowOffset : 0;
              $inputvalue = isset($previousrows[$rowOffset][$this->columnnumber($comprising->column)]) ?
                          $previousrows[$rowOffset][$this->columnnumber($comprising->column)] :
                          '';
              break;
            case 'text':
              $inputvalue = $comprising->text;
              break;
            case 'field':
              if (! isset($outputrecord->{$comprising->field})) { $this->oops("at row {$this->currentrow}, {$comprising->field} is not a previous field in the same record"); }
              $inputvalue = $outputrecord->{$comprising->field};
              break;
            case 'previousfield':
              if (count($output) == 0) {
                $inputvalue = '';
              } else {
                $previousrecord = $outputgroup[$idxRecord];
                if (! isset($previousrecord->{$comprising->field})) { $this->oops("at row {$this->currentrow}, {$comprising->field} is not a field in the previous record"); }
                $inputvalue = $previousrecord->{$comprising->field};
              }
              break;
            }
            if (! empty($comprising->trimSpaces)) { $inputvalue = trim($inputvalue); }
            if (! empty($inputvalue)) {
              if (! empty($comprising->prefixMinus)) { $inputvalue = '-'.$inputvalue; }
              if (! empty($comprising->appendComma)) { $inputvalue .= ','; }
              if (! empty($comprising->appendSpace)) { $inputvalue .= ' '; }
            }
            $outputvalue .= $inputvalue;            
          }

          /* apply options */
          $omitnextoption = FALSE;
          foreach($field->options as $option) {
            if (! isset($option->item)) { continue; }
            if ($omitnextoption) {
              $omitnextoption = FALSE;
              continue;
            }
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
              $outputvalue = preg_replace($option->matches, isset($option->output) ? $option->output : '', $outputvalue);
              if (is_null($outputvalue)) { self::oops("incorrect regexp '{$option->matches}'"); }
              break;
            case 'replaceString':
              if (empty($option->matches)) { break; }
              $outputvalue = str_replace($option->matches, isset($option->output) ? $option->output : '', $outputvalue);
              break;
            case 'trim':
              $outputvalue = trim($outputvalue);
              break;
            case 'bookkeepersNegative':
              $outputvalue = preg_replace('~^\\(([0-9\\.]*)\\)$~', '-$1', $outputvalue);
              $outputvalue = preg_replace('~^([0-9\\.]*)-$~', '-$1', $outputvalue);
              $outputvalue = preg_replace('~^([0-9\\.]*)\\+$~', '$1', $outputvalue);
              break;
            case 'skipIf':
              if (empty($option->condition)) { break; }
              if ($this->meetscondition($option->condition,
                                        $this->valueOf($option),
                                        $this->comparevalue($option, $rows, $previousrows,
                                                            $outputvalue, $outputrecord, $output),
                                        $previousrows))
              {
                $omitnextoption = TRUE;
              }
              break;
            case 'skipUnless':
              if (empty($option->condition)) { break; }
              if (! $this->meetscondition($option->condition,
                                          $this->valueOf($option),
                                          $this->comparevalue($option, $rows, $previousrows,
                                                              $outputvalue, $outputrecord, $output),
                                          $previousrows))
              {
                $omitnextoption = TRUE;
              }
              break;
            case 'omitIf':
              if (empty($option->condition)) { break; }
              if ($this->meetscondition($option->condition,
                                        $this->valueOf($option),
                                        $this->comparevalue($option, $rows, $previousrows,
                                                            $outputvalue, $outputrecord, $output),
                                        $previousrows))
              {
                continue 3;
              }
              break;
            case 'carryOverIf':
              if (empty($option->condition)) { break; }
              if ($this->meetscondition($option->condition,
                                        $this->valueOf($option),
                                        $this->comparevalue($option, $rows, $previousrows,
                                                            $outputvalue, $outputrecord, $output),
                                        $previousrows))
              {
                $outputvalue = $this->varpath($previousrecords[$idxRecord], $field->name);
              }
              break;
            case 'errorOnValue':
              if (empty($option->condition)) { break; }
              if ($this->meetscondition($option->condition,
                                        $this->valueOf($option),
                                        $this->comparevalue($option, $rows, $previousrows,
                                                            $outputvalue, $outputrecord, $output),
                                        $previousrows))
              {
                $this->oops("at row {$this->currentrow}, '{$outputvalue}', failed errorOnValue check)");
              }
              break;
            case 'convertToNumber':
              if (! is_numeric($outputvalue)) {
                if (! empty($option->errorOnType)) {
                  $this->oops("at row {$this->currentrow}, '{$outputvalue}' is not numeric (failed errorOnType check)");
                }
                $outputvalue = 0;
              } else {
                $outputvalue = (float)$outputvalue;
              }
              if (! empty($option->negate)) { $outputvalue = - $outputvalue; }
              break;
            case 'convertToNumberSum':
              $numbers = preg_split('~[ ,]+~', $outputvalue);
              $total = 0;
              foreach($numbers as $number){
                if (! is_numeric($number)) {
                  if (! empty($option->errorOnType)) {
                    $this->oops("at row {$this->currentrow}, '{$number}' is not numeric (failed errorOnType check)");
                  }
                } else {
                  $total += (float)$number;
                }
              }
              $outputvalue = $total;
              if (! empty($option->negate)) { $outputvalue = - $outputvalue; }
              break;
            case 'convertToDate':
            case 'convertToCustomDate':
              if (empty($option->dateFormatUS) && (strpos($outputvalue, '/') || strpos($outputvalue, '.')) !== FALSE) {
                $date = str_replace(['/','.'], '-', $outputvalue);
                $century = substr(date('Y'), 0, 2);
                $date = preg_replace('~^([0-9]+)-([0-9]+)-([0-9]{2})($|[^0-9])~', "$1-$2-{$century}$3$4", $date);
              } else {
                $date = $outputvalue;
              }
              $time = strtotime($date);
              if ($time === FALSE) {
                if (! empty($option->errorOnType)) {
                  $this->oops("at row {$this->currentrow}, '{$outputvalue}' does not look like a date/time (failed errorOnType check)");
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

          $outputgroup[$idxRecord] = $outputrecord;
          
          $this->varpath($outputrecord, $field->name, $outputvalue); // assign outputvalue to outputrecord
          if (! isset($this->outputtypes[$field->name])) { $this->outputtypes[$field->name] = $outputtype; }
        }

        /* abandon the record if any field condition is met */
        foreach($record->unless as $unless) {
          if ($this->meetscondition($unless->condition, $this->valueOf($unless),
                                    $this->varpath($outputrecord, $unless->field),
                                    $previousrows)) { continue 2; }
        }
        
        /* abandon the record if any ignore row condition based on field is met */
        foreach($this->recipe->ignoreRows as $ignoreRow) {
          if ($ignoreRow->item != 'field') { continue; }
          if ($this->meetscondition($ignoreRow->condition, $this->valueOf($ignoreRow),
                                    $this->varpath($outputrecord, $ignoreRow->name),
                                    $previousrows)) { continue 2; }
        }
        
        /* if everything is OK, save the new record, omitting any fields requested */
        foreach($excludefields as $excludefield) { unset($outputrecord->$excludefield); }
        $output[] = $outputrecord;
      }
    }
    
    if (! empty($this->path)) { fclose($this->fd); }

    return $output;
  }

  /* ================================================== */
  /* now the output functions... */
  
  function output($output, $inputfilename, $cl=FALSE){
    $mimetypes = ['json'=>'application/json',
                  'csv'=>'application/csv',
                  'html'=>'text/html',
                  'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                  'xml'=>'text/xml',
                  'qif'=>'text/plain',
    ];
    $this->cl = $cl;
    $this->elementname = empty($this->recipe->outputName) ?
                       preg_replace('~[^a-z0-9_]~i', '', str_replace('.csv','',$inputfilename)) :
                       $this->recipe->outputName;
    if (! $this->cl && $this->recipe->outputTo != 'string') {
      header("Content-type: {$mimetypes[$this->recipe->outputFormat]}");
      header($this->recipe->outputTo == 'inline' && 
             $this->recipe->outputFormat != 'csv' && $this->recipe->outputFormat != 'xlsx' ?
               'Content-disposition: inline' :
               'Content-disposition: attachment; filename="'.str_replace('.csv', ".{$this->recipe->outputFormat}",
                                                                         $inputfilename).'"');
      header('Content-Transfer-Encoding: binary');
      if (! empty($this->encoding)) { header("X-Comment-Original-CSV-Encoding: {$this->encoding}"); }
      ob_end_flush();
    }

    switch($this->recipe->outputFormat){
    case 'json': $s = $this->outputjson($output); break;
    case 'csv': $s = $this->outputcsv($output); break;
    case 'html': $s = $this->outputhtml($output); break;
    case 'xlsx': $s = $this->outputxlsx($output); break;
    case 'xml': $s = $this->outputxml($output); break;
    case 'qif': $s = $this->outputqif($output); break;
    }

    if ($this->recipe->outputTo == 'string') { return $s; } else { echo $s; }
  }

  function outputjson($output){
    if (empty($this->recipe->outputBulkElastic)) {
      $s = ! empty($this->recipe->outputStyle) && $this->recipe->outputStyle == 'pretty' ?
         json_encode($output, JSON_PRETTY_PRINT) : json_encode($output);
    } else {
      $s = '';
      foreach($output as $record) {
        $s .= json_encode(['create'=>['_type'=>$this->elementname]])."\n".json_encode($record)."\n";
      }
    }
    return $s;
  }

  function flattenkeys($ks, $k){
    $kk = '';
    $p = '';
    foreach(array_merge($ks, [$k]) as $k1) {
      $kk .= is_string($k1) ? "{$p}{$k1}" : "[{$k1}]";
      $p = '.';
    }
    return $kk;
  }
  
  function outputcsv($output) {
    $encoding = empty($this->recipe->outputEncoding) ? NULL : $this->recipe->outputEncoding;
    $s = ! empty($this->recipe->outputHeaderRow) ? $this->csv_keys($output[0], '', [], $encoding)."\n" : '';
    foreach($output as $record) { $s .= $this->csv_values($record, '', $encoding)."\n"; }
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
      if (is_object($v) || is_array($v)) {
        $s .= $this->csv_values($v, $p, $encoding);
      } else {
        $s .= $this->csv_value($v, $p, $encoding);
      }
      $p = ',';
    }
    return $s;
  }

  function csv_keys($data, $p, $ks, $encoding){
    $s = '';
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v) || is_array($v)) {
        $s .= $this->csv_keys($v, $p, array_merge($ks, [$k]), $encoding);
      } else if (! empty($ks)) {        
        $s .= $this->csv_value($this->flattenkeys($ks, $k), $p, $encoding);
      } else {
        $s .= $this->csv_value($k, $p, $encoding);
      }
      $p = ',';
    }
    return $s;
  }
  
  function outputxlsx($output) {
    $x = new \XLSXWriter();
    if (! empty($this->recipe->outputHeaderRow) && ! empty($output)) {
      $x->writeSheetHeader('Sheet1', $this->xlsx_flattenkeys($output[0], []));
    }
    foreach($output as $row) {
      $x->writeSheetRow('Sheet1', $this->xlsx_flatten($row));
    }
    $fn = tempnam ('/tmp', 'jcomma-xlsx-');
    $x->writeToFile($fn);
    return file_get_contents($fn);
  }

  function xlsx_flatten($data){
    $a = [];
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v) || is_array($v)) {
        $a = array_merge($a, $this->xlsx_flatten($v));
      } else {
        $a[] = $v;
      }
    }
    return $a;
  }

  function xlsx_flattenkeys($data, $ks){
    $a = [];
    if (is_object($data)) { $data = (array)$data; }
    foreach($data as $k => $v) {
      if (is_object($v) || is_array($v)) {
        $a = array_merge($a, $this->xlsx_flattenkeys($v, array_merge($ks, [$k])));
      } else if (! empty($ks)) {
        $type = gettype($v);
        $k1 = $this->flattenkeys($ks,$k);
        if ($type == 'string' && isset($this->outputtypes[$k1])) { $type = $this->outputtypes[$k1]; }
        if ($type == 'double' || $type == 'float') { $type = '0.00'; }
        $a[$k1] = $type;
      } else {
        $type = gettype($v);
        if ($type == 'string' && isset($this->outputtypes[$k])) { $type = $this->outputtypes[$k]; }
        if ($type == 'double' || $type == 'float') { $type = '0.00'; }
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
<title>jcomma: html output</title>
<style>
body { font-family: Arial, Helvetica, sans-serif; }
table { border-collapse: collapse; }
td { border: 1px solid black; padding: 2px; min-height: 18px; }
</style>
</head>
<body>
<table>

EOD;
    if (! empty($this->recipe->outputHeaderRow)) {
      $s .= "<thead><tr>\n";
      $s .= $this->html_keys($output[0], []);
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
      if (is_object($v) || is_array($v)) {
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
      if (is_object($v) || is_array($v)) {
        $s .= $this->html_keys($v, array_merge($ks, [$k]));
      } else if (! empty($ks)) {
        $s .= $this->html_value($this->flattenkeys($ks, $k));
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
      $s .= empty($this->recipe->outputXMLElements) ?
         $this->xmlify_attributes($record, $this->elementname) :
         $this->xmlify_elements($record, $this->elementname);
    }
    return "{$s}</{$this->elementname}s>\n";
  }

  function xmlify_attributes($record, $key){
    $s = "<{$key}";
    $subordinates = [];
    if (is_object($record)) { $record = (array)$record; }
    foreach($record as $k=>$v) {
      if (is_object($v)) {
        $subordinates[$k][] = $v;
      } else if (is_array($v)) {
        foreach($v as $i => $va) {
          if (is_object($va)) {
            $va->_index = $i;
            $subordinates[$k][] = $va;
          } else {
            $vaa = new \stdClass();
            $vaa->_index = $i;
            $vaa->_value = $va;
            $subordinates[$k][] = $vaa;
          }
        }
      } else {
        $s .= " {$k}='".htmlspecialchars($v)."'";
      }
    }
    $s .= '>';
    foreach ($subordinates as $k => $va) {
      foreach($va as $i => $v) { $s .= $this->xmlify_attributes($v, $k); }
    }
    return $s."</{$key}>\n";
  }

  function xmlify_elements($record, $key, $index=NULL){
    $index = is_null($index) ? '' : " _index=\"{$index}\"";
    $s = "<{$key}{$index}>\n";
    if (is_array($record)) {
      foreach($record as $i => $v) {
        /* try to derive a singular name from plural if possible */
        $ka = substr($key,-2) == 'es' ? substr($key,0,-2) : (substr($key,-1) == 's' ? substr($key,0,-1) : $key);
        $s .= $this->xmlify_elements($v, $ka, $i);
      }
    } else {
      if (is_object($record)) { $record = (array)$record; }
      foreach($record as $k=>$v) {
        if (is_object($v) || is_array($v)) { $s .= $this->xmlify_elements($v, $k); }
        else { $s .= "  <{$k}>".htmlspecialchars($v)."</{$k}>\n"; }
      }
    }
    return $s."</{$key}>\n";
  }

  function outputqif($output) {
    $s = '!Type:'.(empty($this->recipe->outputQIFType) ? 'Bank' : $this->recipe->outputQIFType)."\n";
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
