<?php
require_once('init.php');
// From https://msdn.microsoft.com/en-us/library/gg276466.aspx
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:wl="http://apis.live.net/js/2010">
<head>
    <title>Windows Live SDK Sample - Contacts Sample</title>
    <script type="text/javascript" src="http://js.live.net/4.1/loader.js"></script>
    <script type="text/javascript">
var dataContext;
var auth;

// Callback for when the Application successfully loads.
function appLoaded(appLoadedEventArgs) {
    auth = Microsoft.Live.App.get_auth();
}

// Callback for when Sign in completes. Check whether it was successful.
function signInCompleted() {
    if (auth.get_state() === Microsoft.Live.AuthState.failed) {
        Sys.Debug.trace("Authentication failed.");
        return;
    }
    else if (auth.get_state() === Microsoft.Live.AuthState.authenticated) {
        Sys.Debug.trace("Authentication succeeded.");
        dataContext = Microsoft.Live.App.get_dataContext();
        listContacts();
	}
}

function signOutCompleted() {
    // Perform actions upon signing out.
    Sys.Debug.trace("Good-bye.");
}
//Load contacts and list them.
function listContacts() {
    var contactCollection;
    dataContext.loadAll(Microsoft.Live.DataType.contacts, function(args) {
        if (args.get_resultCode() !== Microsoft.Live.AsyncResultCode.success) {
            Sys.Debug.trace("listContacts: Error retrieving contacts. " + args.get_error().message);
            return;
        }
        contactCollection = args.get_data();
        Sys.Debug.trace("listContacts: Successfully retrieved contacts: " + contactCollection.get_length() + " entries");
        // Contacts loaded. Show the displayname for each:
        for (var i = 0; i < contactCollection.get_length(); i++) {
            Sys.Debug.trace("listContacts: Contact " + i + ": " + contactCollection.get_item(i).get_formattedName());
        }
    })
}
</script>
    
</head>
<body>
    <div class="content">
        <h3>PHP OAuth WRAP Sample</h3>
        <div class="help">
            <p>This web page demonstrates how to use PHP and the JavaScript API to sign a user in to Windows Live.</p>
            <!-- Application Control -->
            <wl:app
			    channel-url="<?php echo WRAP_CHANNEL_URL ?>"
			    callback-url="<?php echo WRAP_CALLBACK ?>"
			    client-id="<?php echo WRAP_CLIENT_ID ?>"
			    scope="WL_Profiles.View,WL_Contacts.View"
			    onload="appLoaded">
			</wl:app>
            
            <!-- Sign in control --> 
			<wl:signin signed-in-text="Sign Out" signed-out-text="Sign In" on-signin="signInCompleted" on-signout="signOutCompleted" />
            <!-- User Info control -->
            <wl:userinfo></wl:userinfo>
        </div>
    </div>
<!-- Text area to output debugging and other information -->
<div class="textarea">
    <textarea class="traceConsole" id="TraceConsole" rows="15" cols="100"></textarea>
</div>
</body>
</html>