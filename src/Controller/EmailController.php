<?php

namespace Email\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;

class EmailController extends AbstractActionController
{

    protected $vhm;
    protected $em;
    protected $emailReaderService;

    public function __construct($vhm, $em, $emailReaderService)
    {
        $this->vhm = $vhm;
        $this->em = $em;
        $this->emailReaderService = $emailReaderService;
    }

    public function indexAction()
    {
        $this->layout('layout/beheer');
        $this->vhm->get('headLink')->appendStylesheet('/css/email.css');
        $folder = $this->params()->fromRoute('folder', 'inbox');
        $page = $this->params()->fromRoute('page', 1);

        $pagination = $this->emailReaderService->createPagination($folder, 10, $page, 10);
        $mails = $this->emailReaderService->getMailsFromMailBox($folder, 10, $page);
        $mailBoxes = $this->emailReaderService->getListMailboxes();
        $mailBoxInfo = $this->emailReaderService->getMailboxInfo($folder);

        return new ViewModel(
            array(
                'mails' => $mails,
                'mailBoxes' => $mailBoxes,
                'pagination' => $pagination,
                'folder' => $folder,
                'mailBoxInfo' => $mailBoxInfo,
                'page' => $page
            )
        );
    }

    /**
     *
     * Action to set delete a email
     */
    public function deleteEmailAction()
    {
        $id = (int)$this->params()->fromRoute('id', 0);
        $folder = $this->params()->fromRoute('folder', 'inbox');
        $page = $this->params()->fromRoute('page', 1);
        if (empty($id)) {
            return $this->redirect()->toRoute('beheer/email');
        }

        // Remove email
        $result = $this->emailReaderService->deleteMailMessage($id, $folder);
        if ($result === true) {

            $this->flashMessenger()->addSuccessMessage('E-mail verwijderd');
        }
        return $this->redirect()->toRoute('beheer/email', ['folder' => $folder, 'page' => $page]);
    }

    /**
     *
     * Action to set delete a email
     */
    public function deleteEmailsInFolderAction()
    {
        $folder = $this->params()->fromRoute('folder', 'inbox');
        $page = $this->params()->fromRoute('page', 1);
        if (empty($folder)) {
            return $this->redirect()->toRoute('beheer/email');
        }

        // Remove email
        $result = $this->emailReaderService->deleteAllMailsInFolder($folder);
        if ($result === true) {

            $this->flashMessenger()->addSuccessMessage('E-mails verwijderd');
        }
        return $this->redirect()->toRoute('beheer/email', ['folder' => $folder, 'page' => $page]);
    }

    public function showEmailAction()
    {
        $this->layout('layout/beheer');
        $this->vhm->get('headLink')->appendStylesheet('/css/email.css');

        $emailID = (int)$this->params()->fromRoute('id', 0);
        $mailbox = $this->params()->fromRoute('folder', 'inbox');
        $page = $this->params()->fromRoute('page', 1);

        $mailBoxes = $this->emailReaderService->getListMailboxes();

        $mailBoxInfo = $this->emailReaderService->getMailboxInfo($mailbox);
        $emailHeader = $this->emailReaderService->getEmailHeader($mailbox, $emailID);

        $msg = $this->emailReaderService->getmsg($mailbox, $emailID);
        $attachments = $msg['attachments'];

        $pointers = [];
        if ($emailID == 1) {
            $pointers['next'] = 0;
            $pointers['previous'] = 2;
        } else if ($emailID == $mailBoxInfo->Nmsgs) {
            $pointers['next'] = $mailBoxInfo->Nmsgs - 1;
            $pointers['previous'] = 0;
        } else {
            $pointers['next'] = $emailID - 1;
            $pointers['previous'] = $emailID + 1;
        }

        return new ViewModel(
            array(
                'emailID' => $emailID,
                'mailbox' => $mailbox,
                'emailHeader' => $emailHeader,
                'pointers' => $pointers,
                'page' => $page,
                'attachments' => $attachments,
                'mailBoxes' => $mailBoxes
            )
        );
    }

    public function showEmailBodyAction()
    {
        $this->layout('layout/iframe');
        $emailID = (int)$this->params()->fromRoute('id', 0);
        $mailbox = $this->params()->fromRoute('folder', 'inbox');
        if ($emailID != 0 && !empty($mailbox)) {
            $msg = $this->emailReaderService->getmsg($mailbox, $emailID);
            $emailBody["body"] = $msg['htmlmsg'];
        }
        return new ViewModel(
            array(
                'emailID' => $emailID,
                'mailbox' => $mailbox,
                'emailBody' => $emailBody
            )
        );
    }

    public function addFolderAction()
    {
        $this->layout('layout/beheer');
        $mailBoxes = $this->emailReaderService->getListMailboxes();

        if ($this->getRequest()->isPost()) {
            $folderName = $this->getRequest()->getPost()['foldername'];
            $parentFolder = $this->getRequest()->getPost()['parentFolder'];

            if (empty($folderName)) {
                $this->flashMessenger()->addSuccessMessage('Geef een folder naam op!');
                return $this->redirect()->toRoute('beheer/manageEmailfolders', array('action' => 'addFolder'));
            } else {
                $result = $this->emailReaderService->addFolder($parentFolder, $folderName);

                if ($result) {
                    $this->flashMessenger()->addSuccessMessage('Email folder aangemaakt!');
                    return $this->redirect()->toRoute('beheer/email');
                } else {
                    $this->flashMessenger()->addSuccessMessage('Email folder niet aangemaakt!');
                    return $this->redirect()->toRoute('beheer/manageEmailfolders', array('action' => 'addFolder'));
                }
            }
        }


        return new ViewModel(
            array(
                'mailBoxes' => $mailBoxes
            )
        );
    }

    public function deleteFolderAction()
    {
        $mailbox = $this->params()->fromRoute('folder');
        if (!empty($mailbox)) {
            $result = $this->emailReaderService->deleteFolder($mailbox);
            if ($result) {
                $this->flashMessenger()->addSuccessMessage('Email folder verwijderd!');
                return $this->redirect()->toRoute('beheer/email');
            } else {
                $this->flashMessenger()->addSuccessMessage('Email folder niet verwijderd!');
                return $this->redirect()->toRoute('beheer/email');
            }
        } else {
            $this->flashMessenger()->addSuccessMessage('Email folder niet verwijderd!');
            return $this->redirect()->toRoute('beheer/email');
        }
    }

    public function downloadFileAction()
    {

        $emailID = $this->params()->fromRoute('id');
        $mailbox = $this->params()->fromRoute('folder');
        $attachmentId = $this->params()->fromRoute('attachmentId');

        $msg = $this->emailReaderService->getmsg($mailbox, $emailID);
        $attachments = $msg['attachments'];
        $attachment = $attachments[$attachmentId];
        $filename = $attachment['filename'];
        $dataForFile = $attachment['data'];

        header('Content-type: application/x-download');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($dataForFile));
        set_time_limit(0);
        echo $dataForFile;
        exit;
    }

    public function moveEmailToFolderAction()
    {
        $errorMessage = '';
        $currentFolder = $this->params()->fromPost('currentFolder');
        $destinationFolder = $this->params()->fromPost('destinationFolder');
        $emailId = $this->params()->fromPost('emailId');
        $success = $this->emailReaderService->moveEmailToFolder($currentFolder, $emailId, $destinationFolder);
        if(!$success)
        {
            $errorMessage = 'E-mail is niet verplaats, probeer opnieuw!';
        }
        return new JsonModel(
            array(
                'success' => $success,
                'errorMessage' => $errorMessage
            )
        );
    }

}
