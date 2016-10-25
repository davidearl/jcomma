<?php

define ("CURRENT_VERSION", 3);

class jcomma {

  function __construct($path_or_filedescriptor, $recipe) {
    if (is_string($path_or_filedescriptor)) {
      $this->path = $path_or_filedescriptor;
    } else {
      $this->fd = $path_or_filedescriptor;
    }
    $this->recipe = $recipe;
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
      if ($n === FALSE) { self::oops('invalid column letter: '.$l); }
      return $n;
    }
    $n1 = strpos(self::$alphabet, $l[0]);
    $n2 = strpos(self::$alphabet, $l[1]);
    if ($n1 === FALSE || $n2 == FALSE) { self::oops('invalid column letter: '.$l); }
    return $n1 * 26 + $n2;
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
      $this->errors[] = $this->describefield($field, 'is not a one of '.implode(', ', $permitted), $v);
    } else if (! $emptyallowed && $field == '') {
      $this->errors[] = $this->describefield($field, 'is empty');
    }
    return $v;
  }

  function checkcondition($field) {
    $conditions = array('empty', 'white', 'match', 'nomatch', 'eq', 'ne', 'ge', 'le', 'before', 'after');
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
          $o = array();
        }
        if (! isset($o[$f])) {
          if (is_null($default)) { $this->describefield($field, 'is missing'); return NULL; }
          $o[$f] = $last ? $default : (gettype($field[$i+1] == 'integer') ? array() : new stdClass());
        }
        $o = $o[$f];
      } else {
        if (! is_object($o)) {
          $this->errors[] = $this->describefield($field, '- object expected');
          $o = new stdClass();
        }
        if (! isset($o->$f)) {
          if (is_null($default)) { $this->errors[] = $this->describefield($field, 'is missing'); }
          $o->$f = $last ? $default : (gettype($field[$i+1] == 'integer') ? array() : new stdClass());
        }
        $o = $o->$f;
      }
    }
    return $o;
  }
  
  function validate(){
    $this->checkint(array('recipeVersion'), 1, 1);
    if ($this->recipe->recipeVersion > CURRENT_VERSION) {
      $this->oops("The server version ".CURRENT_VERSION." predates the recipe version ({$this->recipe->recipeVersion}) supplied");
    }
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
    for($i = 0; $i < count($this->recipe->ignoreRows); $i++) {
      $this->checkstring(array('ignoreRows', $i, 'item'), array('column', 'field'));
      $this->checkstring(array('ignoreRows', $i, 'name'));
      $this->checkcondition(array('ignoreRows', $i, 'condition'));
    }

    $records = $this->checkarray(array('records'), array());
    if (count($records) == 0) {
      $this->errors[] = 'records array is empty - nothing would be produced';
    }

    for($ir = 0; $ir < count($this->recipe->records); $ir++) {
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
          $test = $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'test'),
                                     array('value','field','column'), TRUE, 'value');
          switch ($test) {
          case 'field':
            $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'field'));
            break;
          case 'column':
            $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'column'));
            break;
          }

          $item = $this->checkstring(array('records', $ir, 'fields', $if, 'options', $io, 'item'),
                                     array('ignoreCurrency','replaceRegExp','replaceString','trim',
                                           'bookkeepersNegative','skipIf','skipUnless','omitIf',
                                           'convertToNumber','convertToDate', 'convertToCustomDate',
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
          case 'skipIf':
          case 'skipUnless':
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
          $this->errors[] = $this->describefield(array('records', $ir, 'fields', $if, 'comprising'), 'is empty, nothing to do for this field');
        }
        for ($ic = 0; $ic < count($comprising); $ic++) {
          $item = $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'item'),
                                     array('column', 'text', 'field'));
          switch($item){
          case 'column':
            $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'column'));
            $this->checkint(array('records', $ir, 'fields', $if, 'comprising', $ic, 'rowOffset'), 0, 0);
            break;
          case 'text':
            $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'text'));          
            break;
          case 'field':
            $this->checkstring(array('records', $ir, 'fields', $if, 'comprising', $ic, 'field'));          
            break;
          }          
        }        
      }
      
    }
        
    return $this->errors;
  }


  function meetscondition($condition, $vrecipe, $vcsv) {
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
    default:
      return FALSE;
    }
  }
  
  function comparevalue($option, $rows, $outputvalue, $outputrecord) {
    $test = empty($option->test) ? 'value' : $option->test;
    switch($test) {
    case 'field':
      if (! isset($outputrecord->{$option->field})) {
        $this->oops("at row {$this->currentrow}, {$option->field} is not a previous field in the same record");
      }
      return $outputrecord->{$option->field};
    case 'column':
      if (! isset($rows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)])) {
        $this->oops("at row {$this->currentrow}, column {$option->column} unknown");
      }
      return $rows[0 /* allow rowOffset here? */][$this->columnnumber($option->column)];
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
          $encoding = mb_detect_encoding($s, array('UTF-8', 'ASCII', 
            'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 
            'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 
            'Windows-1251', 'Windows-1252', 'Windows-1254'));
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
  
  function readrows($n, $exact=FALSE){
    $rows = array();
    for($i = 0; $i < $n; $i++) {
      $row = fgetcsv($this->fd);
      $this->currentrow++;
      if ($row === FALSE) { return FALSE; }
      if (! $exact) {
        /* see if we should ignore this row per condition specified */
        foreach($this->recipe->ignoreRows as $ignoreRow) {
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
          $o = new stdClass();
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

  function convert() {
    if (! empty($this->path)) { $this->fd = fopen($this->path, 'r'); }
    if ($this->fd === FALSE) { self::oops("cannot open csv file"); }

    $this->headings = NULL;
    if ($this->recipe->headerRows > 0) {
      $rows = $this->readrows($this->recipe->headerRows, TRUE /* exactly that number, don't ignore any rows */);
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

    $output = array();
    
    for(;;) {
      $rows = $this->readrows($this->recipe->rowCount);
      if (empty($rows)) { break; }

      /* produce each record required from this group of fields */
      foreach($this->recipe->records as $record) {

        $outputrecord = new stdClass();
        $excludefields = array();
        
        foreach ($record->fields as $field) {

          /* calculate outputvalue for field according to 'comprising' */
          $outputvalue = '';
          $outputtype = 'string';
          if (! empty($field->exclude)) { $excludefields[] = $field->name; }

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
            case 'field':
              if (! isset($outputrecord->{$comprising->field})) { $this->oops("at row {$this->currentrow}, {$comprising->field} is not a previous field in the same record"); }
              $outputvalue .= $outputrecord->{$comprising->field};
              break;
            }
            if (! empty($comprising->appendComma)) { $outputvalue .= ','; }
            if (! empty($comprising->appendSpace)) { $outputvalue .= ' '; }
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
                                          empty($option->value) ? '' : $option->value,
                                          $this->comparevalue($option, $rows, $outputvalue, $outputrecord)))
                {
                  $omitnextoption = TRUE;
                }
                break;
            case 'skipUnless':
                if (empty($option->condition)) { break; }
                if (! $this->meetscondition($option->condition,
                                          empty($option->value) ? '' : $option->value,
                                          $this->comparevalue($option, $rows, $outputvalue, $outputrecord)))
                {
                  $omitnextoption = TRUE;
                }
                break;
            case 'omitIf':
                if (empty($option->condition)) { break; }
                if ($this->meetscondition($option->condition,
                                          empty($option->value) ? '' : $option->value,
                                          $this->comparevalue($option, $rows, $outputvalue, $outputrecord)))
                {
                  continue 3;
                }
                break;
            case 'errorOnValue':
                if (empty($option->condition)) { break; }
                if ($this->meetscondition($option->condition,
                                          empty($option->value) ? '' : $option->value,
                                          $this->comparevalue($option, $rows, $outputvalue, $outputrecord)))
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
            case 'convertToDate':
            case 'convertToCustomDate':
              $date = empty($option->dateFormatUS) ? str_replace('/', '-', $outputvalue) : $outputvalue;
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

          $this->varpath($outputrecord, $field->name, $outputvalue); // assign outputvalue to outputrecord
          if (! isset($this->outputtypes[$field->name])) { $this->outputtypes[$field->name] = $outputtype; }
        }
          
        /* abandon the record if any field condition is met */
        foreach($record->unless as $unless) {
          $unlessvalue = ! isset($unless->value) ? '' : $unless->value;
          if ($this->meetscondition($unless->condition, $unlessvalue,
                                    $this->varpath($outputrecord, $unless->field))) { continue 2; }
        }
        
        /* abandon the record if any ignore row condition based on field is met */
        foreach($this->recipe->ignoreRows as $ignoreRow) {
          if ($ignoreRow->item != 'field') { continue; }
          $ignorevalue = ! isset($ignoreRow->value) ? '' : $ignoreRow->value;
          if ($this->meetscondition($ignoreRow->condition, $ignorevalue,
                                    $this->varpath($outputrecord, $ignoreRow->name))) { continue 2; }
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
    $mimetypes = array('json'=>'application/json',
                       'csv'=>'application/csv',
                       'html'=>'text/html',
                       'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'xml'=>'text/xml',
                       'qif'=>'text/plain',
    );
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
        $s .= json_encode(array('create'=>array('_type'=>$this->elementname)))."\n".json_encode($record)."\n";
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
    $s = ! empty($this->recipe->outputHeaderRow) ? $this->csv_keys($output[0], '', array(), $encoding)."\n" : '';
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
    include_once('PHP_XLSXWriter/xlsxwriter.class.php');
    $x = new XLSXWriter();
    if (! empty($this->recipe->outputHeaderRow) && ! empty($output)) {
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
      if (is_object($v) || is_array($v)) {
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
      if (is_object($v) || is_array($v)) {
        $a = array_merge($a, $this->xlsx_flattenkeys($v, array_merge($ks, [$k])));
      } else if (! empty($ks)) {
        $type = gettype($v);
        $k1 = $this->flattenkeys($ks,$k);
        if ($type == 'string' && isset($this->outputtypes[$k1])) { $type = $this->outputtypes[$k1]; }
        $a[$k1] = $type;
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
    $subordinates = array();
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
            $vaa = new stdClass();
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
