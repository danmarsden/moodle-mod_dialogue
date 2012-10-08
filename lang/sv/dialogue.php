<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Swedish language pack by Patrik Granlöv
 *
 * @package    mod
 * @subpackage dialogue
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmynewentries'] = 'Lägg till mina nya inlägg';
$string['addmynewentry'] = 'Skicka inlägg';
$string['addsubject'] = 'Lägg till ämne';
$string['addsubject_help'] = '<p>Du kan använda den här länken för att lägga till ett ämne till dialogen. Det är bra att ha ett ämne då det håller dialogen på rätt spår och fokuserar svaren på ämnet. Om du vill prata om ett annat ämne är det bättre att stänga den aktuella dialogen och starta en ny om det nya ämnet istället.</p>';
$string['allowmultiple'] = 'Tillåt flera dialoger med samma person';
$string['allowmultiple_help'] = '<p>Detta alternativ tillåter en person att start mer än en dialog åt gången med någon annan. Standardinställningen är Nej, alltså bara en (öppen) dialog mellan två personer åt gången.</p><p>Att tillåta flera dialoger kan resultera i missbruk av funktionen. Vissa kan känna sig "trakasserade" om många öppnar oönskade dialoger med dem.</p>';
$string['allowstudentdialogues'] = 'Tillåt dialoger mellan studenter';
$string['attachment'] = 'Bifogade filer';
$string['cannotadd'] = 'Endast gruppmedlemmar kan öppna en dialog.';
$string['cannotaddall'] = 'Kan inte öppna en dialog med alla deltagare';
$string['close'] = 'Avsluta';
$string['closed'] = 'Avslutad';
$string['closeddialogues'] = 'Avslutade dialoger';
$string['closedialogue'] = 'Avsluta dialog';
$string['closedialogue_help'] = '<h1>Avsluta dialoger</h1><p>Du kan avsluta en dialog när som helst. När den avslutas tas den bort från din lista över öppna dialoger.</p>Du kan fortfarande se de avslutade dialogerna, men du kan inte skriva fler inlägg i dem. De kan dock komma att raderas om du ändrar inställningen för när dialoger ska automatiskt raderas. Om de automatiskt ska raderas så blir det ju naturligtvis så också.</p><p>Om du avslutar denna dialog så måste du starta en ny dialog om du vill fortsätta att "prata" med denna person. Personen kommer återigen synas i listan listan över personer som du kan öppna dialoger med.</p>';
$string['configtrackreadentries'] = 'Välj \'ja\' om du vill registrera läst/oläst för varje användare.';
$string['confirmclosure'] = 'Du tänker avsluta en dialog med {$a}. Avslutade dialoger kan inte öppnas igen. Om du avslutar den kan du fortsätta läsa den men inte skriva fler inlägg där. Du får öppna en ny dialog för att fortsätta &quot;prata&quot; med personen.<br /><br />Är du säker på att du vill avsluta dialogen?';
$string['currentattachment'] = 'Bifogade filer:';
$string['deleteafter'] = 'Radera avslutade dialoger efter (dagar)';
$string['deleteafter_help'] = '<h1>Redara dialoger<h1> <p>Denna tid ställer in efter hur många dagar avslutade dialoger automatiskt raderas. Detta gäller endast avslutade dialoger.</p><p>Om tiden är satt till noll så raderas de inte.</p>';
$string['deleteattachment'] = 'Radera bifogad fil';
$string['dialogue:close'] = 'Avsluta';
$string['dialogue:manage'] = 'Hantera';
$string['dialogue:open'] = 'Öppna';
$string['dialogue:participate'] = 'Delta';
$string['dialogue:participateany'] = 'Delta i vilken dialog som helst';
$string['dialogue:viewall'] = 'Visa vilken dialog som helst';
$string['dialoguebetween'] = 'Dialogue mellan {$a->sender} och {$a->recipient}';
$string['dialogueclosed'] = 'Dialogen är avslutad';
$string['dialogueintro'] = 'Introduktion';
$string['dialoguemail'] = '{$a->userfrom} har skrivit ett nytt inlägg i er dialog \'{$a->dialogue}\'. Du kan läsa dialogen här: {$a->url}';
$string['dialoguemailhtml'] = '{$a->userfrom} har skrivit ett nytt inlägg i er dialog \'<i>{$a->dialogue}</i>\'<br /><br />Du kan <a href="{$a->url}">läsa dialogen här</a>.';
$string['dialoguename'] = 'Dialogtitel';
$string['dialogueopened'] = '<p>Dialog startad med {$a->name}</p><p>Du har {$a->edittime} minuter att redigera om du vill göra några ändringar.</p>';
$string['dialoguetype'] = 'Typ av dialog';
$string['dialoguetype_help'] = '<h1>Typ av dialog</h1> <p>Det finns tre typer av dialoger</p>
<ol><li><strong>Lärare till student</strong> Både lärare och student kan start dialogen. Lärarna ser bara studenter i listan över personer, och studenter ser bara lärarna.</li>
<li><strong>Student till student</b> Lärare är inte alls med i denna typ av dialog.</li>
<li><strong>Alla</b> Alla kan öppna dialoger med vem som helst i rummet. Lärare kan öppna dialoger med andra lärare eller studenter. Studenter kan starta dialoger med andra studenter eller lärare.</li></ol>';
$string['dialoguewith'] = 'Dialog med {$a}';
$string['edittime'] = 'Tid för att redigera (minuter)';
$string['edittime_help'] = '<h1>Tid för att redigera</h1> <p>Så här lång tid efter att inlägget skrevs går det att redigera.</p><p>När tiden har passerat kan användaren inte längre redigera inlägget, och ev epostnotifiering skickas.</p>';
$string['erroremptymessage'] = 'Fel: tomt meddelande.';
$string['everybody'] = 'Alla';
$string['firstentry'] = 'Första inlägget';
$string['furtherinformation'] = 'Mer information';
$string['lastentry'] = 'Senaste inlägget';
$string['maildefault'] = '<h1>Epostnotifiering</h1> <p>Om detta är satt till &quot;Ja&quot; så skickas ett kort epost till mottagaren för varje inlägg. Det innehåller inte själva inlägget utan bara vem det är från och en länk.</p><p>Denna inställningar gäller för alla konversationer i denna dialog-instans.</p>';
$string['mailnotification'] = 'Skicka epostnotifiering vid nya inlägg';
$string['mailnotification_help'] = 'Epostnotifiering Hjälp';
$string['modulename'] = 'Dialog';
$string['modulenameplural'] = 'Dialoger';
$string['multiple_help'] = '<h1>Flera doaloger</h1><p>Detta alternativ tillåter en person att start mer än en dialog åt gången med någon annan. Standardinställningen är Nej, alltså bara en (öppen) dialog mellan två personer åt gången.</p><p>Att tillåta flera dialoger kan resultera i missbruk av funktionen. Vissa kan känna sig "trakasserade" om många öppnar oönskade dialoger med dem.</p>';
$string['multipleconversations'] = 'Tillåt mer än en dialog med varje person';
$string['namehascloseddialogue'] = '{$a} har avslutat dialogen';
$string['newdialogueentries'] = 'Nya inlägg';
$string['newentry'] = 'Nytt inlägg';
$string['noavailablepeople'] = 'Det finns ingen att starta en dialog med.';
$string['noentry'] = 'Inga inlägg';
$string['nopersonchosen'] = 'Ingen person vald';
$string['nosubject'] = 'Inget ämne';
$string['notavailable'] = 'Dialoger är inte tillgängliga för gäster';
$string['notextentered'] = 'Ingen text';
$string['notstarted'] = 'Du har inte öppnat dialogen ännu';
$string['notyetseen'] = 'Inte läst ännu';
$string['numberofentries'] = 'Antal inlägg';
$string['numberofentriesadded'] = '<p>Antal tillagda inlägg: {$a->number}</p><p>Du har {$a->edittime} minuter att redigera om du vill ändra något.</p>';
$string['of'] = 'av';
$string['onwrote'] = '{$a->timestamp} {$a->picture} {$a->author}';
$string['onyouwrote'] = '{$a->timestamp} {$a->picture} skrev du';
$string['open'] = 'Starta';
$string['openadialoguewith'] = 'Starta dialog med';
$string['opendialogue'] = 'Skicka dialog';
$string['opendialogueentries'] = 'Öppna inlägg';
$string['opendialogues'] = 'Öppna dialoger';
$string['otherdialogues'] = 'Andra dialoger';
$string['pane0'] = 'Ny dialog';
$string['pane1'] = '{$a} aktiva dialoger';
$string['pane1one'] = '1 aktiv dialog';
$string['pane3'] = '{$a} avslutade dialoger';
$string['pane3one'] = '1 avslutad dialog';
$string['pluginadministration'] = 'Dialog Administration';
$string['pluginname'] = 'Dialog';
$string['posts'] = 'inlägg';
$string['questions'] = 'Frågor';
$string['questions_help'] = 'Frågehjälp';
$string['replyupdated'] = 'Svar uppdaterat';
$string['seen'] = 'Senast visad {$a}';
$string['sendmailmessages'] = 'Skicka epostnotifiering vid nya inlägg';
$string['status'] = 'Status';
$string['studenttostudent'] = 'Student till student';
$string['subject'] = 'Ämne';
$string['subjectadded'] = 'Ämne tillagt';
$string['teachertostudent'] = 'Lärare till student';
$string['trackdialogue'] = 'Registrera olästa inlägg';
$string['typefirstentry'] = 'Första inlägget';
$string['typefollowup'] = 'Skriv uppföljning här';
$string['typeofdialogue'] = 'Typ av dialog';
$string['typeofdialogue_help'] = 'Typ Hjälp';
$string['typereply'] = 'Skriv svar här';
$string['unread'] = 'Olästa inlägg';
$string['unreadnumber'] = '{$a} olästa inlägg';
$string['unreadone'] = '1 oläst inlägg';
$string['updated'] = '(Senast uppdaterad {$a})';
$string['viewallentries'] = 'Visar {$a} inlägg';
