<?php
namespace DrupalPatchUtils\Command;

use DrupalPatchUtils\RtbcQueue;
use DrupalPatchUtils\DoBrowser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EmailRtbcPatches extends ValidatePatch {

  protected function configure() {
    $this
      ->setName('emailRtbcPatches')
      ->setDescription('Emails RTBC patches.')
      ->addArgument(
        'email',
        InputArgument::REQUIRED,
        'Which email address to send the failed RTBC\'s to?'
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->addArgument(
      'url',
      InputArgument::OPTIONAL,
      'What is the url of the issue to retrieve?'
    );
    $rtbc_queue = new RtbcQueue();
    $issues = $rtbc_queue->getIssueUris();
    $progress = $this->getApplication()->getHelperSet()->get('progress');

    $failed_patches = array();
    $progress->start($output, count($issues));
    foreach ($issues as $item) {
      $input->setArgument('url', $item);
      // Ignore NULL return where checkPatch() is unable to determine if patch
      // applies or not. This normally occurs because the issue does not have
      // a patch.
      if ($this->checkPatch($input, $output) === FALSE) {
        $failed_patches[] = array(
          'issue' => $item,
          'patch' => $this->getPatchName(),
          'output' => $this->getOutput()
        );
      }
      $progress->advance();
    }
    $progress->finish();

    if (count($failed_patches)) {
      if ($input->getArgument('email')) {
        $issues_html = "";
        $email = $input->getArgument('email');
        foreach ($failed_patches as $item) {
          $issues_html .= $item['patch'] . " no longer applies.\n" . $item['output'];
        }
        $message = \Swift_Message::newInstance()
          ->setSubject(
            'Drupal.org - Found ' . count($issues) . ' Failed RTBC\'s'
          )
          ->setFrom(array($email))
          ->setTo(array($email))
          ->setBody($issues_html);
        $transport = \Swift_MailTransport::newInstance();
        $mailer = \Swift_Mailer::newInstance($transport);
        $mailer->send($message);
      }
    }
  }

}
