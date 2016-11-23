package de.traffics.sonnenklartv;

import android.app.ActionBar;
import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Typeface;
import android.os.Bundle;
import android.preference.PreferenceManager;
import android.view.MenuItem;
import android.view.View;
import android.widget.Switch;

public class SettingsActivity extends Activity {

    /**
     * Storage for settings
     */
    SharedPreferences sharedPreferences;

    /**
     * Allow notifications checkbox
     */
    Switch allowNotifications;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_settings);

        ActionBar actionBar = getActionBar();
        actionBar.setDisplayHomeAsUpEnabled(true);

        // get storage for settings
        sharedPreferences = PreferenceManager.getDefaultSharedPreferences(this);

        // get reference of the checkboxes
        allowNotifications = (Switch) findViewById(R.id.allowNotifications);

        // populate checkboxes
        allowNotifications.setChecked(sharedPreferences.getBoolean(QuickstartPreferences.REGISTRATION_ALLOW, false));
    }

    /**
     * switch box: event click
     * @param v
     */
    public void onSwitchClicked(View v) {

        //So, check which CheckBox was Clicked and generated a Click event
        switch(v.getId()) { //get the id of clicked CheckBox
            case R.id.allowNotifications:

                //Is the view (Clicked CheckBox) now checked
                boolean checked = allowNotifications.isChecked();

                // make style for text and other needed identifiers
                if (checked) {
                    allowNotifications.setTypeface(allowNotifications.getTypeface(), Typeface.BOLD_ITALIC);
                } else {
                    allowNotifications.setTypeface(null, Typeface.NORMAL);
                }

                // save the state of allow notification
                sharedPreferences.edit().putBoolean(QuickstartPreferences.REGISTRATION_ALLOW, checked).apply();

                // Start IntentService to register this application with GCM.
                Intent intent = new Intent(this, RegistrationIntentService.class);
                startService(intent);
                break;
            default:
                break;
        }
    }

    /**
     * Back button action
     * @param item
     * @return
     */
    public boolean onOptionsItemSelected(MenuItem item){
        finish();
        return true;
    }
}
