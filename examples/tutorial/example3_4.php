<?php

error_reporting(E_ALL ^ E_NOTICE);

if (!extension_loaded("iup")){
    die("iup extension is unavailable");
};

// sleep(10);

$dialogs = array();

/********************************** Utilities *****************************************/
function str_find($haystack, $needle, $offset = 0, $casesensitive = 1){
    
    if(empty($haystack) || $needle === ""){
        return false;
    }

    if($offset > strlen($haystack)){
        $offset = 0;
    }

    if($casesensitive){
        $pos = strpos($haystack, $needle, $offset);
    }else{
        $pos = stripos($haystack, $needle, $offset);
    }

    return $pos;

}
/********************************** Callbacks *****************************************/
function open_cb($hd){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];
    // or
    // $multitext = IupGetDialogChild($hd,"MULTITEXT");

    $filedlg = IupFileDlg();
    IupSetAttribute($filedlg,"DIALOGTYPE", "OPEN");
    IupSetAttribute($filedlg,"EXTFILTER", "Text Files|*.txt|All Files|*.*|");

    IupPopup($filedlg, IUP_CENTER, IUP_CENTER);

    if (IupGetInt($filedlg, "STATUS") != -1)
    {
        $filename = IupGetAttribute($filedlg, "VALUE");
        $str = file_get_contents($filename);
        if($str === false){
            IupMessage("Error", "Fail when reading from file: ".$filename );
        }else{
            IupSetStrAttribute($multitext, "VALUE", $str);
        }
    }

    IupDestroy($filedlg);

    return IUP_DEFAULT;
}

function saveas_cb($hd){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];
    // or
    // $multitext = IupGetDialogChild($hd,"MULTITEXT");

    $filedlg = IupFileDlg();
    IupSetAttribute($filedlg,"DIALOGTYPE", "SAVE");
    IupSetAttribute($filedlg,"EXTFILTER", "Text Files|*.txt|All Files|*.*|");

    IupPopup($filedlg, IUP_CENTER, IUP_CENTER);

    if (IupGetInt($filedlg, "STATUS") != -1)
    {
        $filename = IupGetAttribute($filedlg, "VALUE");
        $str = IupGetAttribute($multitext, "VALUE");
        $count = IupGetInt($multitext, "COUNT");
        $re = file_put_contents($filename, $str);
        if($re === false){
            IupMessage("Error", "Fail when writing to file: ".$filename);
        }
    }

    IupDestroy($filedlg);

    return IUP_DEFAULT;
}

function goto_ok_cb($bt_ok){

    $line_count = IupGetInt($bt_ok, "TEXT_LINECOUNT");

    $txt = IupGetDialogChild($bt_ok,"LINE_TEXT");
    $line = IupGetInt($txt,"VALUE");

    if($line < 1 || $line >= $line_count){
        IupMessage("Error", "Invalid line number.");
        return IUP_DEFAULT;
    }

    IupSetAttribute(IupGetDialog($bt_ok),"STATUS", "1");

    return IUP_CLOSE;
}

function goto_cancel_cb($bt_cancel){

    IupSetAttribute(IupGetDialog($bt_cancel), "STATUS", "0");

    return IUP_CLOSE;
}

function goto_cb($item_goto){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];

    $line_count = IupGetInt($multitext,"LINECOUNT");

    $lbl = IupLabel(NULL);
    IupSetAttribute($lbl,"TITLE","Line Number [1-".$line_count."]");
    $txt = IupText(NULL);
    IupSetAttribute($txt, "MASK", IUP_MASK_UINT);  /* unsigned integer numbers only */
    IupSetAttribute($txt, "NAME", "LINE_TEXT");
    IupSetAttribute($txt, "VISIBLECOLUMNS", "20");
    $bt_ok = IupButton("OK", NULL);
    $dialogs["BUTTONOK"] = $bt_ok;

    IupSetInt($bt_ok, "TEXT_LINECOUNT", $line_count);
    IupSetAttribute($bt_ok, "PADDING", "10x2");
    IupSetCallback($bt_ok, "ACTION", "goto_ok_cb");
    $bt_cancel = IupButton("Cancel", NULL);
    IupSetCallback($bt_cancel, "ACTION", "goto_cancel_cb");
    IupSetAttribute($bt_cancel, "PADDING", "10x2");

    $hbox = IupHbox(IupFill());
    IupAppend($hbox,$bt_ok);
    IupAppend($hbox,$bt_cancel);
    IupSetAttributes($hbox,"NORMALIZESIZE=HORIZONTAL");

    $vbox = IupVbox($lbl);
    IupAppend($vbox,$txt);
    IupAppend($vbox,$hbox);
    IupSetAttribute($vbox, "MARGIN", "10x10");
    IupSetAttribute($vbox, "GAP", "5");

    $dlg = IupDialog($vbox);
    IupSetAttribute($dlg,"TITLE","Go To Line");
    IupSetAttribute($dlg, "DIALOGFRAME", "Yes");
    IupSetAttributeHandle($dlg, "DEFAULTENTER", $bt_ok);
    IupSetAttributeHandle($dlg, "DEFAULTESC", $bt_cancel);
    IupSetAttributeHandle($dlg, "PARENTDIALOG", IupGetDialog($item_goto));

    IupPopup($dlg, IUP_CENTERPARENT, IUP_CENTERPARENT);

    if(IupGetInt($dlg, "STATUS") === 1){
        $line = IupGetInt($txt,"VALUE");
        $pos = IupTextConvertLinColToPos($multitext,$line,0);
        IupSetInt($multitext, "CARETPOS", $pos);
        IupSetInt($multitext, "SCROLLTOPOS", $pos);
    }

    IupDestroy($dlg);

    return IUP_DEFAULT;
}

function find_next_cb($bt_next){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];

    $str = IupGetAttribute($multitext,"VALUE");
    $find_pos = IupGetInt($multitext, "FIND_POS");

    $txt = IupGetDialogChild($bt_next,"FIND_TEXT");
    $str_to_find = IupGetAttribute($txt,"VALUE");

    $find_case = IupGetDialogChild($bt_next,"Find_CASE");
    $casesensitive = IupGetInt($find_case,"VALUE");

    $pos = str_find($str, $str_to_find, $find_pos, $casesensitive);

    if($pos === false){
        /* try again from the start */
        $find_pos = 0;

        $pos = str_find($str, $str_to_find, $find_pos, $casesensitive);

        if($pos === false){
            IupMessage("Warning", "Text not found.");
            return IUP_DEFAULT;
        }
    }

    $end_pos = $pos + strlen($str_to_find);
    IupSetInt($multitext,"FIND_POS",$end_pos);

    IupSetFocus($multitext);
    IupSetAttribute($multitext,"SELECTIONPOS",$pos.":".$end_pos);

    list($lin,$col) = IupTextConvertPosToLinCol($multitext,$pos);
    $pos = IupTextConvertLinColToPos($multitext,$lin,0); /* position at col=0, just scroll lines */
    IupSetInt($multitext, "SCROLLTOPOS", $pos);

    return IUP_DEFAULT;
}

function find_close_cb($bt_close){

    IupHide(IupGetDialog($bt_close));

    return IUP_DEFAULT;

}
function find_cb($item_find){

    global $dialogs;

    $dlg = $dialogs["FIND_DIALOG"];

    if(empty($dlg)){

        $multitext = $dialogs["MULTITEXT"];

        $txt = IupText(NULL);
        IupSetAttribute($txt, "NAME", "FIND_TEXT");
        IupSetAttribute($txt, "VISIBLECOLUMNS", "20");

        $find_case = IupToggle("Case sensitive",NULL);
        IupSetAttribute($find_case,"NAME","Find_CASE");

        $bt_next = IupButton("Find Next",NULL);
        IupSetAttribute($bt_next,"PADDING","10x2");
        IupSetCallback($bt_next,"ACTION","find_next_cb");

        $bt_close = IupButton("Close",NULL);
        IupSetAttribute($bt_close,"PADDING","10x2");
        IupSetCallback($bt_close,"ACTION","find_close_cb");

        $hbox = IupHbox(IupFill());
        IupAppend($hbox,$bt_next);
        IupAppend($hbox,$bt_close);
        IupSetAttributes($hbox,"NORMALIZESIZE=HORIZONTAL");

        $label = IupLabel("Find What:");
        $vbox = IupVbox($label);
        IupAppend($vbox,$txt);
        IupAppend($vbox,$find_case);
        IupAppend($vbox,$hbox);

        IupSetAttribute($vbox,"MARGIN", "10x10");
        IupSetAttribute($vbox,"GAP", "5");

        $dlg = IupDialog($vbox);
        IupSetAttribute($dlg,"TITLE", "Find");
        IupSetAttribute($dlg, "DIALOGFRAME", "Yes");
        IupSetAttributeHandle($dlg, "DEFAULTENTER", $bt_next);
        IupSetAttributeHandle($dlg, "DEFAULTESC", $bt_close);
        IupSetAttributeHandle($dlg, "PARENTDIALOG", IupGetDialog($item_find));

        $dialogs["FIND_DIALOG"] = $dlg;
    }

    IupShowXY($dlg, IUP_CENTER, IUP_CENTER);

    return IUP_DEFAULT;
}

function font_cb($hd){

    $fontdlg = IupFontDlg();

    $multitext = IupGetDialogChild($hd,"MULTITEXT");

    $font = IupGetAttribute($multitext,"FONT");

    IupSetAttribute($fontdlg,"VALUE",$font);

    IupPopup($fontdlg, IUP_CENTER,IUP_CENTER);

    if(IupGetInt($fontdlg,"STATUS") == 1){
        $font = IupGetAttribute($fontdlg,"VALUE");

        IupSetAttribute($multitext,"FONT",$font);
    }

    IupDestroy($fontdlg);

    return IUP_DEFAULT;
}

function about_cb($hd){
    IupMessage("About", "   Simple Notepad\n\nAutors:\n   Gustavo Lyrio\n   Antonio Scuri");
    return IUP_DEFAULT;
}

function exit_cb($hd){
    return IUP_CLOSE;
}

/********************************** Main *****************************************/

function main()
{
    global $dialogs;

    IupOpen();
    
    IupSetGlobal("UTF8MODE","Yes");
    
    $multitext = IupText(NULL);

    $dialogs["MULTITEXT"] = $multitext;

    IupSetAttribute($multitext, "MULTILINE", "YES");

    IupSetAttribute($multitext, "EXPAND", "YES");

    IupSetAttribute($multitext, "NAME", "MULTITEXT");

    $item_open = IupItem("Open", NULL);
    $item_saveas = IupItem("Save As", NULL);
    $item_exit = IupItem("Exit", NULL);
    $item_find = IupItem("Find..", NULL);
    $item_goto = IupItem("Go To...", NULL);

    $item_font = IupItem("Font...", NULL);
    $item_about = IupItem("About...", NULL);

    IupSetCallback($item_open, "ACTION", "open_cb");
    IupSetCallback($item_saveas, "ACTION", "saveas_cb");
    IupSetCallback($item_exit, "ACTION", "exit_cb");
    IupSetCallback($item_find, "ACTION", "find_cb");
    IupSetCallback($item_goto, "ACTION", "goto_cb");
    IupSetCallback($item_font, "ACTION", "font_cb");
    IupSetCallback($item_about, "ACTION", "about_cb");


    $file_menu = IupMenu($item_open);
    IupAppend($file_menu,$item_saveas);
    IupAppend($file_menu,IupSeparator());
    IupAppend($file_menu,$item_exit);

    $edit_menu = IupMenu($item_find);
    IupAppend($edit_menu,$item_goto);

    $format_menu = IupMenu($item_font);

    $help_menu = IupMenu($item_about);

    $sub_menu_file = IupSubmenu("File", $file_menu);
    $sub_menu_edit = IupSubmenu("Edit", $edit_menu);
    $sub_menu_format = IupSubmenu("Format", $format_menu);
    $sub_menu_help = IupSubmenu("Help", $help_menu);
    
    $menu = IupMenu($sub_menu_file);
    IupAppend($menu,$sub_menu_edit);
    IupAppend($menu,$sub_menu_format);
    IupAppend($menu,$sub_menu_help);

    $vbox = IupVbox($multitext);

    $dlg = IupDialog($vbox);

    IupSetAttributeHandle($dlg, "MENU", $menu);

    IupSetAttribute($dlg, "TITLE", "Simple Notepad");

    IupSetAttribute($dlg, "SIZE", "HALFxHALF");

    /* parent for pre-defined dialogs in closed functions (IupMessage) */
    IupSetAttributeHandle(NULL, "PARENTDIALOG", $dlg);

    IupShowXY($dlg, IUP_CENTERPARENT, IUP_CENTERPARENT);

    IupSetAttribute($dlg, "USERSIZE", NULL);

    IupMainLoop();

    IupClose();

}

main();
