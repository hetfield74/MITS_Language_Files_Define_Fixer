<?php
/**
 * --------------------------------------------------------------
 * File: mits_language_fixer.php
 * Date: 18.12.2025
 * Time: 17:22
 *
 * Author: Hetfield
 * Copyright: (c) 2025 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 * --------------------------------------------------------------
 */

include 'includes/application_top.php';
set_time_limit(0);

$version = 'v1.1.0';
$removed = false;

if (isset($_SESSION['customers_status']['customers_status'])
  && $_SESSION['customers_status']['customers_status'] == '0'
  && defined('DIR_FS_DOCUMENT_ROOT')
) {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    if ($action == 'delfile') {
        $menu_file = DIR_FS_DOCUMENT_ROOT . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'includes/extra/menu/mits_language_fixer.php';
        if (is_file($menu_file)) {
            unlink($menu_file);
        }
        unlink(DIR_FS_DOCUMENT_ROOT . basename($PHP_SELF));
        $removed = true;
    }

    $baseDir = DIR_FS_DOCUMENT_ROOT . 'lang';

    function mits_file_has_defined_guard(string $code, string $const): bool
    {
        return (bool)preg_match(
          '/defined\s*\(\s*([\'"])' . preg_quote($const, '/') . '\1\s*\)/',
          $code
        );
    }

    function mits_fix_language_file(string $file): bool
    {
        $code = file_get_contents($file);
        if ($code === false || $code === '') {
            return false;
        }

        $tokens = token_get_all($code);

        $repls = [];
        $cursor = 0;
        $len = strlen($code);

        $i = 0;
        $n = count($tokens);

        while ($i < $n) {
            $tok = $tokens[$i];
            $text = is_array($tok) ? $tok[1] : $tok;
            $tokLen = strlen($text);

            if (is_array($tok) && $tok[0] === T_STRING && strtolower($tok[1]) === 'define') {
                $defineStart = $cursor;
                $j = $i + 1;
                $jCursor = $cursor + $tokLen;

                while ($j < $n) {
                    $t = $tokens[$j];
                    $tText = is_array($t) ? $t[1] : $t;

                    if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                        $jCursor += strlen($tText);
                        $j++;
                        continue;
                    }
                    break;
                }

                if ($j >= $n) {
                    break;
                }

                $t = $tokens[$j];
                $tText = is_array($t) ? $t[1] : $t;

                if ($tText !== '(') {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $k = $j + 1;
                $kCursor = $jCursor + 1;

                while ($k < $n) {
                    $tt = $tokens[$k];
                    $ttText = is_array($tt) ? $tt[1] : $tt;

                    if (is_array($tt) && ($tt[0] === T_WHITESPACE || $tt[0] === T_COMMENT || $tt[0] === T_DOC_COMMENT)) {
                        $kCursor += strlen($ttText);
                        $k++;
                        continue;
                    }
                    break;
                }

                if ($k >= $n) {
                    break;
                }

                $argTok = $tokens[$k];

                if (!(is_array($argTok) && $argTok[0] === T_CONSTANT_ENCAPSED_STRING)) {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $argText = $argTok[1];
                $constName = substr($argText, 1, -1);

                if (mits_file_has_defined_guard($code, $constName)) {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $p = $i;
                $pCursor = $cursor;
                $parenDepth = 0;
                $foundParen = false;
                $endPos = null;

                while ($p < $n) {
                    $pt = $tokens[$p];
                    $ptText = is_array($pt) ? $pt[1] : $pt;
                    $ptLen = strlen($ptText);

                    if ($ptText === '(') {
                        $parenDepth++;
                        $foundParen = true;
                    } elseif ($ptText === ')') {
                        $parenDepth = max(0, $parenDepth - 1);
                    } elseif ($ptText === ';' && $foundParen && $parenDepth === 0) {
                        $endPos = $pCursor + 1;
                        break;
                    }

                    $pCursor += $ptLen;
                    $p++;
                }

                if ($endPos === null || $endPos > $len) {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $originalCall = substr($code, $defineStart, $endPos - $defineStart);

                $guardPrefix = "defined('{$constName}') || ";
                if (strpos($originalCall, $guardPrefix) === 0) {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $repls[] = [$defineStart, $endPos, $guardPrefix . $originalCall];

                $cursor += $tokLen;
                $i++;
                continue;
            }

            $cursor += $tokLen;
            $i++;
        }

        if (empty($repls)) {
            return false;
        }

        usort($repls, function ($a, $b) {
            return $b[0] <=> $a[0];
        });

        $newCode = $code;
        foreach ($repls as $r) {
            $start = $r[0];
            $end = $r[1];
            $rep = $r[2];
            $newCode = substr($newCode, 0, $start) . $rep . substr($newCode, $end);
        }

        return (file_put_contents($file, $newCode) !== false);
    }

    function mits_map_language_named_file(string $relPath, string $refLang, string $targetLang): string
    {
        $base = basename($relPath);
        if ($base !== $refLang . '.php') {
            return $relPath;
        }

        $dir = dirname($relPath);
        $mapped = $targetLang . '.php';
        if ($dir === '.' || $dir === DIRECTORY_SEPARATOR) {
            return $mapped;
        }
        return $dir . DIRECTORY_SEPARATOR . $mapped;
    }

    function mits_collect_define_calls(string $code): array
    {
        if ($code === '') {
            return [];
        }

        $tokens = token_get_all($code);
        $cursor = 0;
        $len = strlen($code);
        $out = [];

        $i = 0;
        $n = count($tokens);

        while ($i < $n) {
            $tok = $tokens[$i];
            $text = is_array($tok) ? $tok[1] : $tok;
            $tokLen = strlen($text);

            if (is_array($tok) && $tok[0] === T_STRING && strtolower($tok[1]) === 'define') {
                $defineStart = $cursor;

                $j = $i + 1;
                $jCursor = $cursor + $tokLen;
                while ($j < $n) {
                    $t = $tokens[$j];
                    $tText = is_array($t) ? $t[1] : $t;
                    if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                        $jCursor += strlen($tText);
                        $j++;
                        continue;
                    }
                    break;
                }
                if ($j >= $n) {
                    break;
                }
                $t = $tokens[$j];
                $tText = is_array($t) ? $t[1] : $t;
                if ($tText !== '(') {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $k = $j + 1;
                $kCursor = $jCursor + 1;
                while ($k < $n) {
                    $tt = $tokens[$k];
                    $ttText = is_array($tt) ? $tt[1] : $tt;
                    if (is_array($tt) && ($tt[0] === T_WHITESPACE || $tt[0] === T_COMMENT || $tt[0] === T_DOC_COMMENT)) {
                        $kCursor += strlen($ttText);
                        $k++;
                        continue;
                    }
                    break;
                }
                if ($k >= $n) {
                    break;
                }
                $argTok = $tokens[$k];
                if (!(is_array($argTok) && $argTok[0] === T_CONSTANT_ENCAPSED_STRING)) {
                    $cursor += $tokLen;
                    $i++;
                    continue;
                }

                $argText = $argTok[1];
                $constName = substr($argText, 1, -1);

                $p = $i;
                $pCursor = $cursor;
                $parenDepth = 0;
                $foundParen = false;
                $endPos = null;
                while ($p < $n) {
                    $pt = $tokens[$p];
                    $ptText = is_array($pt) ? $pt[1] : $pt;
                    $ptLen = strlen($ptText);

                    if ($ptText === '(') {
                        $parenDepth++;
                        $foundParen = true;
                    } elseif ($ptText === ')') {
                        $parenDepth = max(0, $parenDepth - 1);
                    } elseif ($ptText === ';' && $foundParen && $parenDepth === 0) {
                        $endPos = $pCursor + 1;
                        break;
                    }
                    $pCursor += $ptLen;
                    $p++;
                }

                if ($endPos !== null && $endPos <= $len) {
                    $out[$constName] = substr($code, $defineStart, $endPos - $defineStart);
                }

                $cursor += $tokLen;
                $i++;
                continue;
            }

            $cursor += $tokLen;
            $i++;
        }

        return $out;
    }

    function mits_insert_missing_constants(string $targetFile, array $missingConsts, array $refDefineCalls, string $refLang): bool
    {
        if (!is_file($targetFile) || empty($missingConsts)) {
            return false;
        }

        $code = file_get_contents($targetFile);
        if ($code === false) {
            return false;
        }

        $insertLines = [];
        foreach ($missingConsts as $c) {
            if (!isset($refDefineCalls[$c])) {
                continue;
            }
            $insertLines[] = "defined('{$c}') || " . rtrim($refDefineCalls[$c]);
        }

        if (empty($insertLines)) {
            return false;
        }

        $block = "\n\n// --- added by MITS Language Files Define Fixer (missing constants from {$refLang}) ---\n";
        $block .= implode("\n", $insertLines) . "\n";

        $pos = strrpos($code, '?>');
        if ($pos !== false) {
            $newCode = substr($code, 0, $pos) . $block . substr($code, $pos);
        } else {
            $newCode = rtrim($code) . $block;
        }

        return (file_put_contents($targetFile, $newCode) !== false);
    }

    $results = [];
    $missingReport = [];
    $missingInsertedFiles = [];

    if (isset($_POST['run']) && is_dir($baseDir)) {
        $mode = $_POST['mode'] ?? 'guard';

        if ($mode === 'guard') {
        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                if (mits_fix_language_file($file->getPathname())) {
                    $results[] = $file->getPathname();
                }
            }
        }
    }

        if ($mode === 'missing') {
            $refLang = ($_POST['ref_lang'] ?? 'german') === 'english' ? 'english' : 'german';
            $doInsert = isset($_POST['do_insert']) && (string)$_POST['do_insert'] === '1';
            $copyMissingFiles = isset($_POST['copy_missing_files']) && (string)$_POST['copy_missing_files'] === '1';

            $refDir = $baseDir . DIRECTORY_SEPARATOR . $refLang;
            if (is_dir($refDir)) {
                $refFiles = [];
                $refIt = new RecursiveIteratorIterator(
                  new RecursiveDirectoryIterator($refDir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($refIt as $rf) {
                    if ($rf->isFile() && strtolower($rf->getExtension()) === 'php') {
                        $rel = substr($rf->getPathname(), strlen($refDir) + 1);
                        $refFiles[$rel] = $rf->getPathname();
                    }
                }

                $langs = glob($baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
                foreach ($langs as $langDir) {
                    $lang = basename($langDir);
                    if ($lang === 'german' || $lang === 'english') {
                        continue;
                    }

                    foreach ($refFiles as $rel => $refFile) {
                        $mappedRel = mits_map_language_named_file($rel, $refLang, $lang);
                        $targetFile = $langDir . DIRECTORY_SEPARATOR . $mappedRel;
                        if (!is_file($targetFile)) {
                            if ($doInsert && $copyMissingFiles) {
                                $dir = dirname($targetFile);
                                if (!is_dir($dir)) {
                                    @mkdir($dir, 0775, true);
                                }

                                $copied = false;
                                if (is_dir($dir)) {
                                    $copied = @copy($refFile, $targetFile);
                                }

                                if ($copied) {
                                    mits_fix_language_file($targetFile);
                                    $missingInsertedFiles[] = $targetFile;

                                    $missingReport[] = [
                                      'lang' => $lang,
                                      'file' => $targetFile,
                                      'missing' => ['(Datei fehlte komplett und wurde aus ' . $refLang . ' übernommen)'],
                                      'inserted' => true,
                                    ];
                                } else {
                                    $missingReport[] = [
                                      'lang' => $lang,
                                      'file' => $targetFile,
                                      'missing' => ['(Datei fehlt komplett - Übernahme aus ' . $refLang . ' fehlgeschlagen)'],
                                      'inserted' => false,
                                    ];
                                }
                            } else {
                                $missingReport[] = [
                                  'lang' => $lang,
                                  'file' => $targetFile,
                                  'missing' => ['(Datei fehlt komplett)'],
                                  'inserted' => false,
                                ];
                            }
                            continue;
                        }

                        $refCode = file_get_contents($refFile) ?: '';
                        $tgtCode = file_get_contents($targetFile) ?: '';

                        $refCalls = mits_collect_define_calls($refCode);
                        if (empty($refCalls)) {
                            continue;
                        }

                        $tgtCalls = mits_collect_define_calls($tgtCode);
                        $missing = array_values(array_diff(array_keys($refCalls), array_keys($tgtCalls)));
                        if (empty($missing)) {
                            continue;
                        }

                        sort($missing);
                        $inserted = false;
                        if ($doInsert) {
                            $inserted = mits_insert_missing_constants($targetFile, $missing, $refCalls, $refLang);
                            if ($inserted) {
                                $missingInsertedFiles[] = $targetFile;
                            }
                        }

                        $missingReport[] = [
                          'lang' => $lang,
                          'file' => $targetFile,
                          'missing' => $missing,
                          'inserted' => $inserted,
                        ];
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>MITS Language Define Fixer</title>
  <style>
    body {
      font-family: system-ui, -apple-system, Segoe UI, sans-serif;
      background: #ffe;
      padding: 30px;
      font-size: 14px;
      color: #444;
    }

    code {
      background: #eee;
      padding: 1px 4px;
      border-radius: 3px;
    }

    .box {
      background: #fff;
      border-radius: 8px;
      padding: 20px 25px;
      max-width: 900px;
      margin: 0 auto;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }

    h1 {
      margin-top: 0;
      font-size: 24px;
      text-align: center;
      color: #6a9;
    }

    h2 {
      font-size: 20px;
      color: #6a9;
    }

    .notice {
      background: #ffe;
      border: 1px solid #6a9;
      padding: 12px 15px;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    a.button, button {
      background: #6a9;
      color: #fff;
      border: none;
      padding: 10px 18px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      margin: 4px auto;
      display: inline-block;
    }

    a.button:hover, button:hover {
      background: #598;
    }

    ul {
      margin-top: 15px;
      font-size: 13px;
    }

    li {
      padding: 4px;
    }

    .warning {
      color: #dd5555;
      font-weight: 600;
      border: 1px solid #dd5555;
      border-radius: 6px;
      text-align: center;
      background: yellow;
    }

    .btn-icon {
      width: 20px;
      height: 20px;
      fill: currentColor;
      vertical-align: -3px;
      margin-right: 6px;
    }

    .text-icon {
      width: 22px;
      height: 22px;
      fill: #6a9;
      vertical-align: -4px;
      margin-right: 6px;
    }

    .warn-icon {
      width: 22px;
      height: 22px;
      fill: #d55;
      vertical-align: -4px;
      margin-right: 6px;
    }

    .success-icon {
      width: 20px;
      height: 20px;
      fill: #2f6f61;
      vertical-align: -4px;
      margin-right: 6px;
    }

    .success {
      color: #2f6f61;
      font-weight: 600;
      font-size: 16px;
    }

    .github-info {
      text-align: center;
      padding: 10px;
    }

    .github-info a {
      color: #2f6f61;
    }

    .github-info a:hover {
      color: #333;
    }

    form {
      text-align: center;
    }

    i.fa-solid {
      font-size: 16px;
    }

    .warning i.fa-solid {
      font-size: 22px;
    }

    .mits-scrolltop {
      position: fixed;
      right: 18px;
      bottom: 18px;
      width: 44px;
      height: 44px;
      border: 0;
      border-radius: 999px;
      background: #6a9;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .22);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .mits-scrolltop:hover {
      background: #598
    }

    .mits-scrolltop__icon {
      width: 22px;
      height: 22px
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
</head>
<body>
<div class="box">
  <div style="text-align:center">
    <a href="https://www.merz-it-service.de/" title="Gehe zur Homepage von MerZ IT-SerVice">
      <img src="https://www.merz-it-service.de/images/logo.png" border="0" alt="Logo von MerZ IT-SerVice" title="MerZ IT-SerVice">
    </a>
  </div>
  <h1>MITS Language Files Define Fixer <?php
      echo $version; ?></h1>
  <div class="github-info"><a href="https://github.com/hetfield74/MITS_Language_Files_Define_Fixer"><i class="fa-solid fa-link" aria-hidden="true"></i> MITS Language Files Define Fixer @GitHub</a></div>
  <div class="notice">
      <?php
      if ($removed) :
          ?>
        <div class="warning">
          <p>
            <svg class="warn-icon" viewBox="0 0 24 24">
              <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2zm0-4h-2v-4h2z" />
            </svg>
            <strong>Das Modul wurde erfolgreich vom Server entfernt!</strong>
            <svg class="warn-icon" viewBox="0 0 24 24">
              <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2zm0-4h-2v-4h2z" />
            </svg>
          </p>
        </div>

      <?php
      else:
          ?>
        <h2>
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          Hinweis:
        </h2><p>
        Dieses Skript dient dazu, Sprachdateien der modified eCommerce Shopsoftware zu pr&uuml;fen und &auml;ndert
        <code>define(&apos;KONSTANTE&apos;, &apos;Text&apos;);</code>-Anweisungen automatisch in die sichere Kurzschreibweise
        <code>defined(&apos;KONSTANTE&apos;) || define(&apos;KONSTANTE&apos;, &apos;Text&apos;);</code> umzuwandeln. </p>

        <h2>
          <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
          Warum ist das sinnvoll?
        </h2><p>
        In vielen Shops werden eigene Sprachdateien &uuml;ber <i>auto_include()</i> eingebunden &ndash; h&auml;ufig zus&auml;tzlich zu den Standard-Sprachdateien.
        Dabei kommt es sehr schnell zu doppelten Definitionen von Sprachkonstanten, was in PHP zu: </p>

        <ul>
          <li><code>Notice: Constant XXX already defined</code></li>
          <li><code>Warning: Constant XXX already defined</code></li>
        </ul>

        <p>f&uuml;hrt.</p>

        <p>
          Diese Meldungen tauchen nicht nur im Frontend, sondern vor allem auch massiv in den Log-Files auf
          und k&ouml;nnen diese unn&ouml;tig aufbl&auml;hen. </p>

        <p>Durch die Absicherung jeder Konstante mit <code>defined()</code> wird:</p>

        <ul>
          <li>mehrfaches Definieren zuverl&auml;ssig verhindert</li>
          <li>Log-Spam vermieden</li>
          <li>die Nutzung eigener Sprachdateien deutlich sauberer und wartungsfreundlicher</li>
        </ul>

        <p>
          Gerade bei Shops mit vielen Erweiterungen oder eigenen Sprachdateien ist diese Absicherung dringend zu empfehlen.
        </p>

        <div class="warning">
          <p>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <strong>Unbedingt vorher ein Backup des Sprachordners erstellen!</strong>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <br>
            <strong>Nutzung auf eigene Gefahr!</strong>
          </p>
        </div>
      <?php
      endif;
      ?>

  </div>

    <?php
    if (!$removed && isset($_SESSION['customers_status']['customers_status']) && $_SESSION['customers_status']['customers_status'] == '0') : ?>
      <form method="post">
        <div style="text-align:left;max-width:900px;margin:0 auto 10px auto;">
          <fieldset style="border:1px solid #6a9;border-radius:8px;padding:12px 14px;background:#ffe;">
            <legend style="padding:0 8px;color:#2f6f61;font-weight:600;">Aktion</legend>
            <label style="display:block;margin:6px 0;">
              <input type="radio" name="mode" value="guard" <?php echo (($_POST['mode'] ?? 'guard') === 'guard') ? 'checked' : ''; ?> />
              Defines absichern (defined() || define())
            </label>
            <label style="display:block;margin:6px 0;">
              <input type="radio" name="mode" value="missing" <?php echo (($_POST['mode'] ?? 'guard') === 'missing') ? 'checked' : ''; ?> />
              Fehlende Konstanten in Nicht-DE/EN Sprachen finden (und optional einf&uuml;gen)
            </label>

            <div style="margin:10px 0 0 22px;padding:10px;border-left:3px solid #6a9;">
              <div style="margin-bottom:8px;">Quelle f&uuml;r fehlende Konstanten:</div>
              <label style="margin-right:14px;">
                <input type="radio" name="ref_lang" value="german" <?php echo (($_POST['ref_lang'] ?? 'german') === 'german') ? 'checked' : ''; ?> /> german
              </label>
              <label>
                <input type="radio" name="ref_lang" value="english" <?php echo (($_POST['ref_lang'] ?? 'german') === 'english') ? 'checked' : ''; ?> /> english
              </label>

              <div style="margin-top:10px;">
                <label>
                  <input type="checkbox" name="do_insert" value="1" <?php echo (isset($_POST['do_insert']) && (string)$_POST['do_insert'] === '1') ? 'checked' : ''; ?> />
                  Fehlende Konstanten automatisch einf&uuml;gen
                <div style="margin-top:6px;margin-left:22px;">
                  <label>
                    <input type="checkbox" name="copy_missing_files" value="1" <?php echo (isset($_POST['copy_missing_files']) && (string)$_POST['copy_missing_files'] === '1') ? 'checked' : ''; ?> />
                    Falls eine Datei komplett fehlt: komplette Datei aus der Referenzsprache &uuml;bernehmen
                  </label>
                </div>
                </label>
              </div>
            </div>
          </fieldset>
        </div>

        <button type="submit" name="run" value="1">
          <i class="fa-solid fa-gear" aria-hidden="true"></i>
          Ausf&uuml;hren
        </button>
        <button type="submit" name="action" value="delfile" style="background: #d55;">
          <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
          Modul vom Server l&ouml;schen &raquo;
        </button>
        <p style="text-align:center;font-size:11px;margin-top:20px;">
          &copy; by <a href="https://www.merz-it-service.de/">
            <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (MerZ IT-SerVice)</span>
          </a>
        </p>
      </form>

        <?php
        if (!empty($results) && (($_POST['mode'] ?? 'guard') === 'guard')): ?>
          <div class="box">
            <p class="success">
              <i class="fa-solid fa-circle-check mits-ico" aria-hidden="true"></i>
              Fertig! Ge&auml;nderte Dateien:
            </p>
            <ul>
                <?php
                foreach ($results as $file): ?>
                  <li><?php
                      echo htmlspecialchars($file); ?></li>
                <?php
                endforeach; ?>
            </ul>
          </div>
        <?php
        elseif (isset($_POST['run']) && (($_POST['mode'] ?? 'guard') === 'guard')): ?>
          <div class="box">
            <p class="success">
              <i class="fa-solid fa-circle-check mits-ico" aria-hidden="true"></i>
              Keine &Auml;nderungen notwendig <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
            </p>
          </div>
        <?php
        endif;
        ?>

        <?php
        if (isset($_POST['run']) && (($_POST['mode'] ?? 'guard') === 'missing')): ?>
          <div class="box">
            <p class="success">
              <i class="fa-solid fa-list-check" aria-hidden="true"></i>
              Ergebnis: Fehlende Konstanten in Nicht-DE/EN Sprachen
            </p>

            <?php if (empty($missingReport)): ?>
              <p class="success">
                <i class="fa-solid fa-circle-check mits-ico" aria-hidden="true"></i>
                Keine fehlenden Konstanten gefunden.
              </p>
            <?php else: ?>
              <ul>
                <?php foreach ($missingReport as $row): ?>
                  <li>
                    <strong><?php echo htmlspecialchars($row['lang']); ?></strong> &ndash;
                    <?php echo htmlspecialchars($row['file']); ?>
                    <?php if (!empty($row['missing']) && $row['missing'][0] !== '(Datei fehlt komplett)'): ?>
                      <br>
                      Fehlend (<?php echo count($row['missing']); ?>):
                      <code><?php echo htmlspecialchars(implode(', ', $row['missing'])); ?></code>
                      <?php if ($row['inserted']): ?>
                        <span class="success">&nbsp;(&uuml;bernommen)</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <br>
                      <span class="warning" style="display:inline-block;padding:2px 6px;">Datei fehlt komplett</span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>

              <?php if (!empty($missingInsertedFiles)): ?>
                <p class="success" style="margin-top:18px;">
                  <i class="fa-solid fa-circle-check mits-ico" aria-hidden="true"></i>
                  Dateien, in die etwas eingef&uuml;gt wurde:
                </p>
                <ul>
                  <?php foreach (array_values(array_unique($missingInsertedFiles)) as $f): ?>
                    <li><?php echo htmlspecialchars($f); ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

    <?php
    elseif (!$removed): ?>
      <div class="warning">
        <p>
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          Sie haben keine Administrator-Rechte!
        </p>
      </div>
    <?php
    endif; ?>
  <div style="text-align: center; margin: 0 auto; padding: 10px;">
    <a class="button" href="<?php
    echo xtc_href_link(FILENAME_DEFAULT); ?>">
      <i class="fa-solid fa-house mits-ico" aria-hidden="true"></i>
      Zur Startseite des Shops
    </a>
  </div>

</div>
<button type="button" id="mitsScrollTop" class="mits-scrolltop" aria-label="Nach oben scrollen">
  <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
</button>

<script>
  (function () {
    var btn = document.getElementById('mitsScrollTop');
    if (!btn) return;

    btn.addEventListener('click', function () {
      try {
        window.scrollTo({top: 0, behavior: 'smooth'});
      } catch (e) {
        window.scrollTo(0, 0);
      }
    });
  })();
</script>
</body>
</html>