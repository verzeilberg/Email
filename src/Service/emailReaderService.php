<?php

namespace Email\Service;

use function imap_headerinfo;
use function imap_open;

class emailReaderService implements emailReaderServiceInterface
{

    // imap server connection
    public $conn;
    // inbox storage and inbox message count
    private $inbox;
    private $msg_cnt;
    // email login credentials
    private $server;
    private $user;
    private $pass;
    private $port = 143; // adjust according to server settings
    //------------------------------------
    private $charset;
    private $htmlmsg;
    private $plainmsg;
    private $attachments = [];


    protected $config;

    public function __construct($config) {
        $this->config = $config;
        $this->server = $config['email_settings']['server'];
        $this->user = $config['email_settings']['user'];
        $this->pass = $config['email_settings']['password'];
    }

    /*
     * Connect to mailbox
     *
     * @var $inbox inbox to connect to
     *
     * @return resource
     *
     */

    public function connect($inbox = null)
    {
        return imap_open('{' . $this->server . '/notls}' . $inbox, $this->user, $this->pass);
    }

    /*
     * Get mailboxes
     *
     * @return array
     *
     */

    public function getListMailboxes()
    {
        $mailBoxes = [];
        $connection = $this->connect();
        $list = imap_list($connection, '{' . $this->server . '/notls}', "*");
        $this->close($connection);
        if (is_array($list)) {
            sort($list);
            foreach ($list as $index => $val) {
                $mailbox = str_replace('{' . $this->server . '/notls}', '', imap_utf7_decode($val));
                $connection = $this->connect($mailbox);
                $result = $this->getMailboxInfo($mailbox);
                $mailBoxes[$index]['mailbox'] = $mailbox;
                $mailBoxes[$index]['unreadMessages'] = $result->Unread;
            }
            return $mailBoxes;
        } else {
            echo "imap_list failed: " . imap_last_error() . "\n";
        }
    }

    /*
     * Get mailbox info
     *
     * @var $connection connection to mailbox
     *
     * @return object
     *
     */

    public function getMailboxInfo($mailbox = 'INBOX')
    {
        $connection = $this->connect($mailbox);
        $result = imap_mailboxmsginfo($connection);
        $result->Mailbox = str_replace('{' . $this->server . ':' . $this->port . '/imap/notls/user="' . $this->user . '"}', '', $result->Mailbox);
        return $result;
    }

    /*
     * Get e-mails form the specified e-mail box
     *
     * @var $mailbox selected mailbox
     * @var $itemsPage how many items to show on page
     * @var $currentPage current page
     *
     * @return array
     *
     */

    public function getMailsFromMailBox($mailbox = null, $itemsPage = 10, $currentPage = 1)
    {
        $connection = $this->connect($mailbox);
        $this->msg_cnt = imap_num_msg($connection);

        $start = $this->msg_cnt - ($currentPage * ($itemsPage) - 1);
        if ($start < 1) {
            $start = 1;
        }
        $end = $this->msg_cnt - ($itemsPage * ($currentPage - 1));
        $mails = array_reverse(imap_fetch_overview($connection, $start . ":" . $end, 0));
        $in = array();
        foreach ($mails AS $mail) {
            $structure = $this->getEmailStructure($connection, $mail->msgno);
            $in[] = array(
                'index' => $mail->msgno,
                'header' => imap_headerinfo($connection, $mail->msgno),
                'structure' => $structure
            );
        }
        $this->close($connection);
        return $in;
    }

    /*
     * Get structure of the e-mail
     *
     * @var $connection connection to imap mailbox
     * @var $index id of the e-mail message
     *
     * @return string
     *
     */

    public function getEmailStructure($connection, $index)
    {
        $structure = imap_fetchstructure($connection, $index);
        return $structure;
    }

    /*
     * Get e-mail message flag to delete and delete message
     *
     * @var $index email message id
     * @var $mailbox connection to imap mailbox
     *
     * @return string
     *
     */

    public function deleteMailMessage($index = null, $mailbox = null)
    {
        $connection = $this->connect($mailbox);
        $result = imap_delete($connection, $index);
        imap_expunge($connection);
        return $result;
    }

    /*
     * Get e-mail message flag to delete and delete message
     *
     * @var $index email message id
     * @var $mailbox connection to imap mailbox
     *
     * @return string
     *
     */

    public function deleteAllMailsInFolder($mailbox = null)
    {
        if (!empty($mailbox)) {
            $connection = $this->connect($mailbox);
            $result = imap_delete($connection, '1:*');
            return $result;
        } else {
            return false;
        }
    }

    /*
     * Set the flag of the e-mail to read
     *
     * @var $connection connection to imap mailbox
     * @var $index id of the e-mail message
     *
     * @return boolean
     *
     */

    public function setSeenEmail($connection, $index)
    {
        return imap_setflag_full($connection, $index, "\\Seen \\Flagged", ST_UID);
    }

    /*
     * Close the connection to the imap server
     *
     * @return void
     *
     */

    public function close($connection)
    {
        $this->inbox = array();
        $this->msg_cnt = 0;
        imap_close($connection);
    }

    /*
     * Set the flag of the e-mail to read
     *
     * @var $mbox connection to imap mailbox
     * @var $messageid id of the e-mail message
     *
     * @return string
     *
     */

    public function retrieve_message($mbox, $messageid)
    {
        $message = array();

        $header = imap_header($mbox, $messageid);
        $structure = imap_fetchstructure($mbox, $messageid);

        $message['subject'] = $header->subject;
        $message['fromaddress'] = $header->fromaddress;
        $message['toaddress'] = $header->toaddress;
        $message['ccaddress'] = $header->ccaddress;
        $message['date'] = $header->date;

        if ($this->check_type($structure)) {
            $message['body'] = imap_fetchbody($mbox, $messageid, "1"); ## GET THE BODY OF MULTI-PART MESSAGE
            if (!$message['body']) {
                $message['body'] = '[NO TEXT ENTERED INTO THE MESSAGE]\n\n';
            }
        } else {
            $message['body'] = imap_body($mbox, $messageid);
            if (!$message['body']) {
                $message['body'] = '[NO TEXT ENTERED INTO THE MESSAGE]\n\n';
            }
        }

        return $message;
    }

    /*
     * Check if the message is a multi part or not
     *
     * @var $structure structure of the e-mail message
     *
     * @return boolean
     *
     */

    public function check_type($structure)
    { ## CHECK THE TYPE
        if ($structure->type == 1) {
            return (true); ## YES THIS IS A MULTI-PART MESSAGE
        } else {
            return (false); ## NO THIS IS NOT A MULTI-PART MESSAGE
        }
    }

    /*
     * Create pagination
     *
     * @var $mailbox mailbox to use pagination
     * @var $itemsPage how many e mails a page
     * @var $currentPage current page
     * @var $pageRange how many pagination itmes show on page
     *
     * @return array
     *
     */

    public function createPagination($mailbox = null, $itemsPage = 10, $currentPage = 1, $pageRange = 10)
    {
        $connection = $this->connect($mailbox);
        $totalItems = imap_num_msg($connection);
        $totalPages = ceil($totalItems / $itemsPage);
        $arr2 = str_split($totalPages); // convert string to an array
        $endNumber = end($arr2);
        $endRange = $totalPages - $endNumber;

        $pagination = [];

        $arr = str_split($currentPage); // convert string to an array
        $calcNumber = end($arr);


        if (count($arr) > 1) {
            $backward = $currentPage - $calcNumber;
        } else {
            $backward = $currentPage - ($calcNumber - 1);
        }
        $forward = $currentPage + ($pageRange - $calcNumber);

        $previousPage = $currentPage - 1;
        $nextPage = $currentPage + 1;

        $pageRangeStart = $currentPage;
        $pageRangeEnd = $currentPage + $pageRange;

        $pagination['currentPage'] = $currentPage;
        $pagination['previousPage'] = $previousPage;
        $pagination['nextPage'] = $nextPage;
        $pagination['totalPages'] = $totalPages;
        $pagination['pageRangeStart'] = $backward;
        $pagination['pageRangeEnd'] = $forward;
        $pagination['pageRange'] = $pageRange;
        $pagination['endRange'] = $endRange;
        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination['pages'][$i] = $i;
        }
        return $pagination;
    }

    public function getEmailHeader($mailbox, $index)
    {
        $connection = $this->connect($mailbox);
        $emailHeader = imap_headerinfo($connection, $index);
        return $emailHeader;
    }

    public function addFolder($parentMailbox, $mailboxName)
    {
        $connection = $this->connect($parentMailbox);
        if (!empty($parentMailbox)) {
            $newFolder = $parentMailbox . '.' . $mailboxName;
        } else {
            $newFolder = $mailboxName;
        }

        $folders = imap_listmailbox($connection, "{localhost:143}", "*");
        if (!in_array('{localhost:143}' . $mailboxName, $folders)) {
            $result = imap_createmailbox($connection, imap_utf7_encode("{" . $this->server . ":" . $this->port . "}" . $newFolder));
        } else {
            $result = false;
        }
        imap_close($connection);
        return $result;
    }

    public function deleteFolder($mailbox)
    {

        $connection = $this->connect($mailbox);
        $result = imap_deletemailbox($connection, "{" . $this->server . ":" . $this->port . "}" . $mailbox);
        return $result;
    }

    public function getmsg($mailbox, $mid)
    {
        $mbox = $this->connect($mailbox);
        // HEADER
        $h = imap_headerinfo($mbox, $mid);
        // BODY
        $s = imap_fetchstructure($mbox, $mid);
        if (!property_exists($s,'parts'))  // simple
            $this->getpart($mbox, $mid, $s, 0);  // pass 0 as part-number
        else {  // multipart: cycle through each part
            foreach ($s->parts as $partno0 => $p)
                $this->getpart($mbox, $mid, $p, $partno0 + 1);
        }
        //Build message array
        $totalMsg = [];
        $totalMsg['charset'] = $this->charset;
        $totalMsg['htmlmsg'] = $this->htmlmsg;
        $totalMsg['plainmsg'] = $this->plainmsg;
        $totalMsg['attachments'] = $this->attachments;

        return $totalMsg;
    }

    public function getpart($mbox, $mid, $p, $partno)
    {
        // DECODE DATA
        $data = ($partno) ?
            imap_fetchbody($mbox, $mid, $partno) : // multipart
            imap_body($mbox, $mid);  // simple
        // Any part may be encoded, even plain text messages, so check everything.
        if ($p->encoding == 4)
            $data = quoted_printable_decode($data);
        elseif ($p->encoding == 3)
            $data = base64_decode($data);

        // PARAMETERS
        // get all parameters, like charset, filenames of attachments, etc.
        $params = array();
        if ($p->parameters)
            foreach ($p->parameters as $x)
                $params[strtolower($x->attribute)] = $x->value;
        if (property_exists($p, 'dparameters'))
            foreach ($p->dparameters as $x)
                $params[strtolower($x->attribute)] = $x->value;

        // ATTACHMENT
        // Any part with a filename is an attachment,
        // so an attached text file (type 0) is not mistaken as the message.
        if (array_key_exists('filename', $params) || array_key_exists('name', $params)) {
            // filename may be given as 'Filename' or 'Name' or both
            $filename = ($params['filename']) ? $params['filename'] : $params['name'];
            // filename may be encoded, so see imap_mime_header_decode()
            $this->attachments[] = [
                'filename' => $filename,
                'data' => $data
            ];  // this is a problem if two files have same name
        }

        // TEXT
        if ($p->type == 0 && $data) {
            // Messages may be split in different parts because of inline attachments,
            // so append parts together with blank row.
            if (strtolower($p->subtype) == 'plain')
                $this->plainmsg .= trim($data) . "\n\n";
            else
                $this->htmlmsg .= $data . "<br><br>";
            $this->charset = $params['charset'];  // assume all parts are same charset
        }

        // EMBEDDED MESSAGE
        // Many bounce notifications embed the original message as type 2,
        // but AOL uses type 1 (multipart), which is not handled here.
        // There are no PHP functions to parse embedded messages,
        // so this just appends the raw source to the main message.
        elseif ($p->type == 2 && $data) {
            $this->plainmsg .= $data . "\n\n";
        }

        // SUBPART RECURSION
        if (property_exists($p, 'parts')) {
            foreach ($p->parts as $partno0 => $p2)
                $this->getpart($mbox, $mid, $p2, $partno . '.' . ($partno0 + 1));  // 1.2, 1.2.1, etc.
        }
    }

    /*
     * Move e-mail to selected e-mail folder
     *
     * @var $mailbox connection to imap mailbox
     * @var $mid email message id
     * @var $newMailbox destination mailbox
     *
     * @return void
     *
     */
    public function moveEmailToFolder($mailbox, $mid, $newMailbox)
    {
        $connection = $this->connect($mailbox);
        $result = imap_mail_move($connection, $mid, $newMailbox);
        if($result) {
            imap_expunge($connection);
        }
        imap_close($connection);

        return $result;

    }

}
