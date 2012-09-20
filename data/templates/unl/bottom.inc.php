<?php
// Append and flush the buffer started in top.inc.php
$GLOBALS['unlTemplatedPage']->maincontentarea = ob_get_clean();

// Start a buffer to capture the bottom part of maincontent. Will be closed below.
ob_start();
?>


<!--following code is generated by templates/bottom.inc.php-->
<div id="bottom">
<?php echo $GLOBALS['footerMessage'].' ';?>
</div>


<?php
// Append and flush the buffer for the bottom part of maincontent started above.
$GLOBALS['unlTemplatedPage']->maincontentarea .= ob_get_clean();

$GLOBALS['unlTemplatedPage']->leftcollinks = '<h3>Related Links</h3><ul>';
$GLOBALS['unlTemplatedPage']->leftcollinks .= '<li><a href="http://ucomm.unl.edu/">University Communications</a></li>';
$GLOBALS['unlTemplatedPage']->leftcollinks .= '<li><a href="https://sourceforge.net/projects/semanticscuttle/">' . T_("Propulsed by ") . ' SemanticScuttle</a>' . '</li>';

if ($GLOBALS['enableWebsiteThumbnails']) {
    // Licence to the thumbnails provider (OBLIGATORY IF YOU USE ARTVIPER SERVICE)
    $GLOBALS['unlTemplatedPage']->leftcollinks .= 'Thumbnails by <a href="http://www.artviper.net">webdesign</a>';
}
$GLOBALS['unlTemplatedPage']->leftcollinks .= '</ul>';

$GLOBALS['unlTemplatedPage']->contactinfo = '<h3>Contact Us</h3>
                                              <p><strong>University of Nebraska-Lincoln</strong><br />
                                              1400 R Street<br />
                                              Lincoln, NE 68588<br />
                                              402-472-7211</p>';

$GLOBALS['unlTemplatedPage']->footercontent = '&copy; 2012 University of Nebraska–Lincoln | Lincoln, NE 68588 | 402-472-7211 |
                        <a href="http://www.unl.edu/ucomm/aboutunl/" title="Click here to know more about UNL">About UNL</a> |
                        <a href="http://www1.unl.edu/comments/" title="Click here to direct your comments and questions">comments?</a>
                        <br />UNL is an equal opportunity employer with a comprehensive plan for diversity.
                        Find out more: <a href="https://employment.unl.edu/" title="Employment at UNL">employment.unl.edu</a>';

echo $GLOBALS['unlTemplatedPage'];