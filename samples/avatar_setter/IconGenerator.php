<?php
/**
 * File: IconGenerator.php
 * Project: freshdesk-solutions
 * User: blake, http://www.blakerobertson.com
 * Date: 6/13/13
 * Time: 5:15 AM
 */

define("DEFAULT_FONT","Arial.ttf"); // Author used Futura BdCnBT Bold

/**
 * @param $name
 * @param $org
 * @param null $file set to null to print to output stream (instead of saving to file), or specify a file path.
 *             if no path is specified it creates a temporary file.
 * @return mixed
 */
function createIcon($name, $org, $file='TEMP', $font=DEFAULT_FONT) {
    // ####################### BEGIN USER EDITS #######################
    $imagewidth = 150;
    $imageheight = 150;
    $fontsize = "64";
    $fontangle = "0";
    // ######################## END USER EDITS ########################

    if( empty($font) ) {
        $font = DEFAULT_FONT;
    }

    $textcolor = colorize( $name );
    $backgroundcolor = "FFFFFF"; // default is white
    if( !empty($org) ) {
        $backgroundcolor = colorize( $org );
    }
    $text = getInitials( $name );

    ### Convert HTML backgound color to RGB
    if( preg_match( "/([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i", $backgroundcolor, $bgrgb ) )
    {$bgred = hexdec( $bgrgb[1] );   $bggreen = hexdec( $bgrgb[2] );   $bgblue = hexdec( $bgrgb[3] );}

    ### Convert HTML text color to RGB
    if( preg_match( "/([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i", $textcolor, $textrgb ) )
    {$textred = hexdec( $textrgb[1] );   $textgreen = hexdec( $textrgb[2] );   $textblue = hexdec( $textrgb[3] );}

    ### Create image
    $im = imagecreate( $imagewidth, $imageheight );

    ### Declare image's background color
    //$bgcolor = imagecolorallocate($im, $bgred,$bggreen,$bgblue);
    $bgcolor = imagecolorallocate($im,255,255,255);

    ### Declare image's text color
    $fontcolor = imagecolorallocate($im, $textred,$textgreen,$textblue);

    ### Get exact dimensions of text string
    $box = @imageTTFBbox($fontsize,$fontangle,$font,$text);

    ### Get width of text from dimensions
    $textwidth = abs($box[4] - $box[0]);

    ### Get height of text from dimensions
    $textheight = abs($box[5] - $box[1]);

    ### Get x-coordinate of centered text horizontally using length of the image and length of the text
    $xcord = ($imagewidth/2)-($textwidth/2)-2;

    ### Get y-coordinate of centered text vertically using height of the image and height of the text
    $ycord = ($imageheight/2)+($textheight/2);

    //error_log("x/y" . $xcord . $ycord);

    ### Declare completed image with colors, font, text, and text location
//    imagettftext ( $im, $fontsize, $fontangle, $xcord, $ycord, $fontcolor, $font, $text );

    $stroke_color = imagecolorallocate($im, 0, 0, 0);
    imagettfstroketext($im, $fontsize, $fontangle, $xcord, $ycord, $fontcolor, $stroke_color, $font, $text, 1);

    $orgBarColor = imagecolorallocate($im, $bgred,$bggreen,$bgblue);
    imagefilledrectangle($im, 0, $ycord+10, 150,150, $orgBarColor );

    if( $file == null ) {
        imagepng($im);
    }
    else {
        if( $file == 'TEMP') {
            $file = tempnam(sys_get_temp_dir(), 'FreshIcon');
        }
        imagepng($im,$file);
    }

    ### Close the image
    imagedestroy($im);

    return $file;
}

function getInitials($name) {
    $initials = '';
    $var_split = explode(" ", trim($name));
    foreach ($var_split as $temp) {
        $initials .= $temp{0};
    }
    return substr(strtoupper($initials),0,2); // limit to 2 chars
}

/**
 * Takes a string, does a md5 hash of it to get a random color
 * @param $str
 * @return string
 */
function colorize($str) {
    $color = "FFFFFF";
    if( !empty($str) ) {
        $hash = md5($str);
        $color = substr($hash,0,6);
    }
    return $color;
}


// Draw a border
function drawBorder(&$img, &$color, $thickness = 1)
{
    $x1 = 0;
    $y1 = 0;
    $x2 = ImageSX($img) - 1;
    $y2 = ImageSY($img) - 1;

    for($i = 0; $i < $thickness; $i++)
    {
        ImageRectangle($img, $x1++, $y1++, $x2--, $y2--, $color);
    }
}

// Not used...
function visibleText($color) {
    $color = trim($color);
    if ($color[0] == '#') {
        $color = substr($color, 1);
        $pound = '#';
    }

    if (strlen($color) == 3) {
        $colors = array(hexdec($color[0] . $color[0]), hexdec($color[1] . $color[1]), hexdec($color[2] .
            $color[2]));
    } elseif (strlen($color) == 6) {
        $colors = array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2)));
    } else {
        return $pound . $color;
    }

    if (array_sum($colors) > 255 * 1.5) {
        return $pound . '000';
    } else {
        return $pound . 'FFF';
    };
};

/**
 * Writes the given text with a border into the image using TrueType fonts.
 * @author John Ciacia
 * @param image An image resource
 * @param size The font size
 * @param angle The angle in degrees to rotate the text
 * @param x Upper left corner of the text
 * @param y Lower left corner of the text
 * @param textcolor This is the color of the main text
 * @param strokecolor This is the color of the text border
 * @param fontfile The path to the TrueType font you wish to use
 * @param text The text string in UTF-8 encoding
 * @param px Number of pixels the text border will be
 * @see http://us.php.net/manual/en/function.imagettftext.php
 */
function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px) {

    for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
        for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
            $bg = imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);

    return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
}


?>