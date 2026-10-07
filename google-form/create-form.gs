/**
 * Creates the PCA member-details Google Form.
 * Paste into https://script.google.com (New project), click Run > createPcaForm,
 * approve the permissions, then open View > Logs for the form links.
 */
function createPcaForm() {
  var form = FormApp.create('Pest Control Association - Member Details');
  form.setDescription('Please share your details for the association website. The association will review them before anything is published.');
  form.setCollectEmail(false);
  form.setConfirmationMessage('Thank you! Your details have been received. The association will review them and update the website.');

  form.addListItem().setTitle('I am a').setRequired(true)
    .setChoiceValues(['Member (company)', 'Committee member', 'Region committee member']);
  form.addTextItem().setTitle('Full name').setRequired(true);
  form.addTextItem().setTitle('Company name');
  form.addTextItem().setTitle('Position in the association (if any)');
  form.addTextItem().setTitle('Region / City').setRequired(true);
  form.addTextItem().setTitle('Address');

  form.addTextItem().setTitle('Mobile').setRequired(true)
    .setValidation(FormApp.createTextValidation()
      .setHelpText('Enter a valid mobile number (10 digits).')
      .requireTextMatchesPattern('^[+]?[0-9 -]{10,15}$').build());
  form.addTextItem().setTitle('Email').setRequired(true)
    .setValidation(FormApp.createTextValidation()
      .setHelpText('Enter a valid email address.')
      .requireTextIsEmail().build());

  form.addTextItem().setTitle('Member since (year)')
    .setValidation(FormApp.createTextValidation()
      .setHelpText('Enter a 4-digit year.')
      .requireTextMatchesPattern('^[0-9]{4}$').build());
  form.addTextItem().setTitle('Pest control licence number');

  // Apps Script cannot create file-upload questions. Add "Your photo" manually
  // (Add question > File upload, images only, max 1 file, 10 MB) - see README.
  form.addTextItem().setTitle('Photo link (only if you cannot upload a photo)')
    .setHelpText('Optional: a Google Drive / shareable link to your photo.');

  form.addCheckboxItem().setTitle('Consent').setRequired(true)
    .setChoiceValues(['I agree that my name, company, city and photo may be shown on the association website.']);

  Logger.log('Edit link (you): ' + form.getEditUrl());
  Logger.log('Share link (members): ' + form.getPublishedUrl());
}
