$('table#email tr.clickEmailRow td:not(:last-child)').click(function () {
    var emailID = $(this).parent('tr').data('id');
    var mailbox = $(this).parent('tr').data('mailbox');
    window.location.href = "/beheer/email/show/" + mailbox + '/' + emailID;

});