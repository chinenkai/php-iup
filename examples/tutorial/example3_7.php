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

function config_recent_cb($ih){

    global $dialogs;

    $filename = IupGetAttribute($ih,"TITLE");

    // echo $filename;
    $str = file_get_contents($filename);

    if($str){
        $multitext = $dialogs["MULTITEXT"];
        IupSetAttribute($multitext,"VALUE",$str);
    }

    var_dump(IupGetAttribute($ih,"APP_FILENAME"));

    return IUP_DEFAULT;
}

function dialog_key_any_cb($ih,$c){
    if($c == K_cO){
        open_cb($ih);
    }else if($c == K_cS){
        saveas_cb($ih);
    }else if($c == K_cF){
        find_cb($ih);
    }else if($c == K_cG){
        goto_cb($ih);
    }else 
    return IUP_CONTINUE;
}
function multitext_caret_cb($ih,$lin,$col){

    $lbl_statusbar = IupGetDialogChild($ih, "STATUSBAR");
    IupSetAttribute($lbl_statusbar, "TITLE", "Lin $lin, Col $col");
    return IUP_DEFAULT;
}

function open_cb($ih){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];
    // or
    // $multitext = IupGetDialogChild($ih,"MULTITEXT");

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
            $config = $dialogs["CONFIG"];
            IupConfigRecentUpdate($config,$filename);

            IupSetStrAttribute($multitext, "VALUE", $str);
        }
    }

    IupDestroy($filedlg);

    return IUP_DEFAULT;
}

function saveas_cb($ih){

    global $dialogs;

    $multitext = $dialogs["MULTITEXT"];
    // or
    // $multitext = IupGetDialogChild($ih,"MULTITEXT");

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
        }else{
            $config = $dialogs["CONFIG"];
            IupConfigRecentUpdate($config,$filename);
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

    $hbox = IupHbox(IupFill(),$bt_ok,$bt_cancel);
    // IupAppend($hbox,$bt_ok);
    // IupAppend($hbox,$bt_cancel);
    IupSetAttributes($hbox,"NORMALIZESIZE=HORIZONTAL");

    $vbox = IupVbox($lbl,$txt,$hbox);
    // IupAppend($vbox,$txt);
    // IupAppend($vbox,$hbox);
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

        $hbox = IupHbox(IupFill(),$bt_next,$bt_close);
        // IupAppend($hbox,$bt_next);
        // IupAppend($hbox,$bt_close);
        IupSetAttributes($hbox,"NORMALIZESIZE=HORIZONTAL");

        $label = IupLabel("Find What:");
        $vbox = IupVbox($label,$txt,$find_case,$hbox);
        // IupAppend($vbox,$txt);
        // IupAppend($vbox,$find_case);
        // IupAppend($vbox,$hbox);

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

function font_cb($ih){
    global $dialogs;
    $config = $dialogs["CONFIG"];

    $fontdlg = IupFontDlg();

    $multitext = IupGetDialogChild($ih,"MULTITEXT");

    $font = IupGetAttribute($multitext,"FONT");

    IupSetAttribute($fontdlg,"VALUE",$font);

    IupPopup($fontdlg, IUP_CENTER,IUP_CENTER);

    if(IupGetInt($fontdlg,"STATUS") == 1){
        $font = IupGetAttribute($fontdlg,"VALUE");

        IupSetAttribute($multitext,"FONT",$font);

        IupConfigSetVariableStr($config,"MainWindow","Font",$font);
    }

    IupDestroy($fontdlg);

    return IUP_DEFAULT;
}

function about_cb($ih){
    IupMessage("About", "   Simple Notepad\n\nAutors:\n   Gustavo Lyrio\n   Antonio Scuri");
    return IUP_DEFAULT;
}

function exit_cb($ih){

    global $dialogs;

    $dlg = $dialogs["DIALOG"];
    $config = $dialogs["CONFIG"];
    IupConfigDialogClosed($config,$dlg,"MainWindow");
    IupConfigSave($config);
    IupDestroy($config);

    return IUP_CLOSE;
}

/********************************** Main *****************************************/

function main()
{
    global $dialogs;

    IupOpen();

    IupImageLibOpen();
    
    IupSetGlobal("UTF8MODE","Yes");

    $config = IupConfig();
    $dialogs["CONFIG"] = $config;
    IupSetAttribute($config,"APP_NAME","simple_notepad");
    IupConfigLoad($config);
    
    $multitext = IupText(NULL);

    $dialogs["MULTITEXT"] = $multitext;

    IupSetAttribute($multitext, "MULTILINE", "YES");
    IupSetAttribute($multitext, "EXPAND", "YES");
    IupSetAttribute($multitext, "NAME", "MULTITEXT");

    $font = IupConfigGetVariableStr($config,"MainWindow","Font");
    if(!empty($font)){
        IupSetAttribute($multitext,"FONT",$font);
    }

    $lbl_statusbar = IupLabel("Lin 1, Col 1");
    IupSetAttribute($lbl_statusbar, "NAME", "STATUSBAR");  
    IupSetAttribute($lbl_statusbar, "EXPAND", "HORIZONTAL");
    IupSetAttribute($lbl_statusbar, "PADDING", "10x5");

    $item_open = IupItem("&Open...\tCtrl+O", NULL);
    $btn_open = IupButton(NULL,NULL);
    IupSetAttribute($btn_open, "IMAGE", "IUP_FileOpen");
    IupSetAttribute($btn_open, "FLAT", "Yes");
    IupSetAttribute($btn_open, "TIP", "Open (Ctrl+O)");
    IupSetAttribute($btn_open, "CANFOCUS", "No");

    $item_saveas = IupItem("Save &As...\tCtrl+S", NULL);
    $btn_save = IupButton(NULL, NULL);
    IupSetAttribute($btn_save, "IMAGE", "IUP_FileSave");
    IupSetAttribute($btn_save, "FLAT", "Yes");
    IupSetAttribute($btn_save, "TIP", "Save (Ctrl+S)");
    IupSetAttribute($btn_save, "CANFOCUS", "No");

    $item_exit = IupItem("E&xit", NULL);

    $item_find = IupItem("&Find..\tCtrl+F", NULL);
    $btn_find = IupButton(NULL, NULL);
    IupSetAttribute($btn_find, "IMAGE", "IUP_EditFind");
    IupSetAttribute($btn_find, "FLAT", "Yes");
    IupSetAttribute($btn_find, "TIP", "Find (Ctrl+F)");
    IupSetAttribute($btn_find, "CANFOCUS", "No");

    $toolbar_hb = IupHbox($btn_open,$btn_save,IupSetAttributes(IupLabel(NULL), "SEPARATOR=VERTICAL"),$btn_find);
    // IupAppend($toolbar_hb,$btn_save);
    // IupAppend($toolbar_hb,IupSetAttributes(IupLabel(NULL), "SEPARATOR=VERTICAL"));
    // IupAppend($toolbar_hb,$btn_find);
    IupSetAttribute($toolbar_hb, "MARGIN", "5x5");
    IupSetAttribute($toolbar_hb, "GAP", "2");


    $item_goto = IupItem("&Go To...\tCtrl+G", NULL);

    $item_font = IupItem("&Font...", NULL);
    $item_about = IupItem("&About...", NULL);

    IupSetCallback($item_open, "ACTION", "open_cb");
    IupSetCallback($btn_open, "ACTION", "open_cb");
    IupSetCallback($item_saveas, "ACTION", "saveas_cb");
    IupSetCallback($btn_save, "ACTION", "saveas_cb");
    IupSetCallback($item_exit, "ACTION", "exit_cb");
    IupSetCallback($item_find, "ACTION", "find_cb");
    IupSetCallback($btn_find, "ACTION", "find_cb"); 
    IupSetCallback($item_goto, "ACTION", "goto_cb");
    IupSetCallback($item_font, "ACTION", "font_cb");
    IupSetCallback($item_about, "ACTION", "about_cb");
    IupSetCallback($multitext, "CARET_CB", "multitext_caret_cb");

    $recent_menu = IupMenu();

    $file_menu = IupMenu($item_open,$item_saveas,IupSeparator(),IupSubmenu("Recent &Files", $recent_menu),$item_exit);
    // IupAppend($file_menu,$item_saveas);
    // IupAppend($file_menu,IupSeparator());
    // IupAppend($file_menu,IupSubmenu("Recent &Files", $recent_menu));
    // IupAppend($file_menu,$item_exit);

    $edit_menu = IupMenu($item_find,$item_goto);
    // IupAppend($edit_menu,$item_goto);

    $format_menu = IupMenu($item_font);

    $help_menu = IupMenu($item_about);

    $sub_menu_file = IupSubmenu("&File", $file_menu);
    $sub_menu_edit = IupSubmenu("&Edit", $edit_menu);
    $sub_menu_format = IupSubmenu("F&ormat", $format_menu);
    $sub_menu_help = IupSubmenu("&Help", $help_menu);
    
    $menu = IupMenu($sub_menu_file,$sub_menu_edit,$sub_menu_format,$sub_menu_help);
    // IupAppend($menu,$sub_menu_edit);
    // IupAppend($menu,$sub_menu_format);
    // IupAppend($menu,$sub_menu_help);

    $vbox = IupVbox($toolbar_hb,$multitext,$lbl_statusbar);
    // IupAppend($vbox,$multitext);
    // IupAppend($vbox,$lbl_statusbar);

    $dlg = IupDialog($vbox);

    $dialogs["DIALOG"] = $dlg;

    IupSetAttributeHandle($dlg, "MENU", $menu);

    IupSetAttribute($dlg, "TITLE", "Simple Notepad");

    IupSetAttribute($dlg, "SIZE", "HALFxHALF");

    IupSetCallback($dlg, "CLOSE_CB", "exit_cb");

    /* parent for pre-defined dialogs in closed functions (IupMessage) */
    IupSetAttributeHandle(NULL, "PARENTDIALOG", $dlg);

    // IupSetCallback($dlg, "K_cO", "open_cb");
    // IupSetCallback($dlg, "K_cS", "saveas_cb");
    // IupSetCallback($dlg, "K_cF", "find_cb");
    // IupSetCallback($dlg, "K_cG", "goto_cb");
    IupSetCallback($dlg, "K_ANY", "dialog_key_any_cb");

    IupConfigRecentInit($config, $recent_menu, "config_recent_cb", 10);

    IupConfigDialogShow($config, $dlg, "MainWindow");
    // IupShowXY($dlg, IUP_CENTERPARENT, IUP_CENTERPARENT);

    IupSetAttribute($dlg, "USERSIZE", NULL);

    IupMainLoop();

    IupClose();

}

main();
