package de.traffics.sonnenklartv;

import android.app.AlertDialog;
import android.app.IntentService;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.preference.PreferenceManager;
import android.util.Log;

import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GooglePlayServicesUtil;
import com.google.android.gms.gcm.GcmPubSub;
import com.google.android.gms.gcm.GoogleCloudMessaging;
import com.google.android.gms.iid.InstanceID;

import org.apache.http.HttpEntity;
import org.apache.http.HttpResponse;
import org.apache.http.HttpVersion;
import org.apache.http.client.HttpClient;
import org.apache.http.client.methods.HttpGet;
import org.apache.http.conn.ClientConnectionManager;
import org.apache.http.conn.scheme.PlainSocketFactory;
import org.apache.http.conn.scheme.Scheme;
import org.apache.http.conn.scheme.SchemeRegistry;
import org.apache.http.conn.ssl.SSLSocketFactory;
import org.apache.http.impl.client.DefaultHttpClient;
import org.apache.http.impl.conn.tsccm.ThreadSafeClientConnManager;
import org.apache.http.params.BasicHttpParams;
import org.apache.http.params.HttpParams;
import org.apache.http.params.HttpProtocolParams;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.security.KeyStore;

public class RegistrationIntentService extends IntentService {

    /**
     * Storage for settings
     */
    SharedPreferences sharedPreferences;

    private static final String TAG = "RegIntentService";
    private static final String[] TOPICS = {"global"};

    public RegistrationIntentService() {
        super(TAG);
    }

    @Override
    protected void onHandleIntent(Intent intent) {
        if (_checkPlayServices()) {
            sharedPreferences = PreferenceManager.getDefaultSharedPreferences(this);

            try {
                // In the (unlikely) event that multiple refresh operations occur simultaneously,
                // ensure that they are processed sequentially.
                synchronized (TAG) {

                    InstanceID instanceID = InstanceID.getInstance(this);
                    String token = instanceID.getToken(getString(R.string.gcm_defaultSenderId),
                            GoogleCloudMessaging.INSTANCE_ID_SCOPE, null);

                    if (sharedPreferences.getBoolean(QuickstartPreferences.REGISTRATION_ALLOW, false)) {
                        _registerToken(token);
                    } else {
                        _unregisterToken(token);
                    }
                }
            } catch (Exception e) {
                Log.d(TAG, "Failed to complete token refresh", e);
                // If an exception happens while fetching the new token or updating our registration data
                // on a third-party server, this ensures that we'll attempt the update at a later time.
                sharedPreferences.edit().putBoolean(QuickstartPreferences.SENT_TOKEN_TO_SERVER, false).apply();
            }
        }
    }

    /**
     * Retrieve the token if not already sent and register
     */
    private void _registerToken(String token) {
        if (!sharedPreferences.getBoolean(QuickstartPreferences.SENT_TOKEN_TO_SERVER, false)) {

            // send registration to server and subscribe to channels
            if (_sendRegistrationToServer(token, true) && _subscribeTopics(token)) {

                // store a boolean that indicates whether the generated token has been sent
                sharedPreferences.edit().putBoolean(QuickstartPreferences.SENT_TOKEN_TO_SERVER, true).apply();
            } else {
                this._triggerDialog();
            }
        }
    }


    /**
     * unregister token
     */
    private void _unregisterToken(String token) {
        if (_sendRegistrationToServer(token, false)) {

            // store a boolean that indicates whether the generated token has been deleted
            sharedPreferences.edit().putBoolean(QuickstartPreferences.SENT_TOKEN_TO_SERVER, false).apply();
        } else {
            this._triggerDialog();
        }
    }

    /**
     * Persist registration to third-party servers.
     *
     * @param token The new token.
     * @return bool
     */
    private boolean _sendRegistrationToServer(String token, Boolean register) {
        String comment = getString(R.string.app_name);

        HttpClient httpclient = getNewHttpClient();
        HttpGet httpget = new HttpGet(getString((register)? R.string.gcm_rest_url_add: R.string.gcm_rest_url_remove) + "&" +
                    getString(R.string.gcm_rest_comment_param) + comment + "&" +
                    getString(R.string.gcm_rest_token_param) + token);

        try {
            HttpResponse response = httpclient.execute(httpget);
            HttpEntity entity = response.getEntity();

            if (entity != null) {
                InputStream inputstream = entity.getContent();
                BufferedReader bufferedreader =
                        new BufferedReader(new InputStreamReader(inputstream));
                StringBuilder stringbuilder = new StringBuilder();

                String currentline = null;
                while ((currentline = bufferedreader.readLine()) != null) {
                    stringbuilder.append(currentline + "\n");
                }
                String result = stringbuilder.toString();
                Log.v("HTTPS REQUEST",result);
                inputstream.close();
            }
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    /**
     * Get HttpClient
     */
    public HttpClient getNewHttpClient() {
        try {
            KeyStore trustStore = KeyStore.getInstance(KeyStore.getDefaultType());
            trustStore.load(null, null);

            MySSLSocketFactory sf = new MySSLSocketFactory(trustStore);
            sf.setHostnameVerifier(SSLSocketFactory.ALLOW_ALL_HOSTNAME_VERIFIER);

            HttpParams params = new BasicHttpParams();
            HttpProtocolParams.setVersion(params, HttpVersion.HTTP_1_1);
            HttpProtocolParams.setContentCharset(params, "UTF-8");

            SchemeRegistry registry = new SchemeRegistry();
            registry.register(new Scheme("http", PlainSocketFactory.getSocketFactory(), 80));
            registry.register(new Scheme("https", sf, 443));

            ClientConnectionManager ccm = new ThreadSafeClientConnManager(params, registry);

            return new DefaultHttpClient(ccm, params);
        } catch (Exception e) {
            return new DefaultHttpClient();
        }
    }

    /**
     * Subscribe to any GCM topics of interest, as defined by the TOPICS constant.
     *
     * @param token GCM token
     * @return bool
     */
    private boolean _subscribeTopics(String token) {
        try {
            for (String topic : TOPICS) {
                GcmPubSub pubSub = GcmPubSub.getInstance(this);
                pubSub.subscribe(token, "/topics/" + topic, null);
            }
            return true;
        } catch(IOException e) {
            return false;
        }
    }

    /**
     * Check the device to make sure it has the Google Play Services APK. If
     * it doesn't, display a dialog that allows users to download the APK from
     * the Google Play Store or enable it in the device's system settings.
     */
    private boolean _checkPlayServices() {
        int resultCode = GooglePlayServicesUtil.isGooglePlayServicesAvailable(this);
        if (resultCode != ConnectionResult.SUCCESS) {
            Log.i(TAG, "This device is not supported.");
            return false;
        }
        return true;
    }

    /**
     * show connection problem dialog
     */
    private void _triggerDialog(){
        Intent intent = new Intent(getApplicationContext(), MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);

        Bundle message = new Bundle();
        message.putInt("error_conn", 1);
        intent.putExtras(message);

        startActivity(intent);
    }
}
