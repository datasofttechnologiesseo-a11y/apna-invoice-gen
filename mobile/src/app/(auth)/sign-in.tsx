import { Link } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useRef, useState } from 'react';
import { Pressable, TextInputInstance, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { TextField } from '@/components/text-field';
import { FEATURES, LINKS } from '@/config';
import { AuthShell } from '@/screens/auth-shell';
import { colors, fonts } from '@/theme';

export default function SignIn() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const pwRef = useRef<TextInputInstance>(null);

  const submit = async () => {
    setErrors({});
    if (!email.trim() || !password) {
      setErrors({ email: !email.trim() ? 'Enter your email.' : '', password: !password ? 'Enter your password.' : '' });
      return;
    }
    setBusy(true);
    try {
      await signIn(email, password);
    } catch (e) {
      const err = e as ApiError;
      setErrors(Object.keys(err.fieldErrors ?? {}).length ? err.fieldErrors : { email: err.message });
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthShell title="Log in to Apna Invoice" subtitle="GST billing and accounts for Indian businesses">
      {FEATURES.googleSignIn ? (
        <>
          <Button label="Sign in with Google" kind="outline" icon="logo-google" onPress={() => {}} />
          <AppText variant="small" color={colors.textMuted} align="center">
            or with email
          </AppText>
        </>
      ) : null}
      <TextField
        label="Email"
        value={email}
        onChangeText={setEmail}
        error={errors.email || undefined}
        autoCapitalize="none"
        autoComplete="email"
        keyboardType="email-address"
        textContentType="username"
        returnKeyType="next"
        onSubmitEditing={() => pwRef.current?.focus()}
        placeholder="you@example.com"
      />
      <TextField
        ref={pwRef}
        label="Password"
        value={password}
        onChangeText={setPassword}
        error={errors.password || undefined}
        secureTextEntry={!show}
        autoComplete="current-password"
        textContentType="password"
        returnKeyType="go"
        onSubmitEditing={submit}
        suffix={
          <Pressable accessibilityRole="button" accessibilityLabel={show ? 'Hide password' : 'Show password'} onPress={() => setShow((s) => !s)} hitSlop={12}>
            <Icon name={show ? 'eye-off-outline' : 'eye-outline'} color={colors.textMuted} />
          </Pressable>
        }
      />
      <Pressable accessibilityRole="link" onPress={() => WebBrowser.openBrowserAsync(LINKS.forgotPassword)} hitSlop={8} style={{ alignSelf: 'flex-end' }}>
        <AppText style={{ fontFamily: fonts.bold, fontSize: 14 }} color={colors.tealDark}>
          Forgot your password?
        </AppText>
      </Pressable>
      <Button label="Log in" onPress={submit} loading={busy} />
      <View style={{ flex: 1, minHeight: 16 }} />
      <AppText align="center" color="#475467" style={{ fontSize: 14 }}>
        New here?{' '}
        <Link href="/sign-up" style={{ fontFamily: fonts.bold, color: colors.tealDark }}>
          Create a free account
        </Link>
      </AppText>
    </AuthShell>
  );
}
