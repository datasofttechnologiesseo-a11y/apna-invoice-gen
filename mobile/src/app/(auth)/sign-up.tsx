import * as WebBrowser from 'expo-web-browser';
import { useRef, useState } from 'react';
import { Pressable, TextInputInstance, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { TextField } from '@/components/text-field';
import { LINKS } from '@/config';
import { AuthShell } from '@/screens/auth-shell';
import { colors, fonts } from '@/theme';

function Check({ on, onToggle, children, label }: { on: boolean; onToggle: () => void; children: React.ReactNode; label: string }) {
  return (
    <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: on }} accessibilityLabel={label} onPress={onToggle} style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start' }} hitSlop={6}>
      <View style={{ width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: on ? colors.teal : colors.borderStrong, backgroundColor: on ? colors.teal : colors.surface, alignItems: 'center', justifyContent: 'center', marginTop: 1 }}>
        {on ? <Icon name="checkmark" size={15} color={colors.onDark} /> : null}
      </View>
      <View style={{ flex: 1 }}>{children}</View>
    </Pressable>
  );
}

/** Same fields as the web sign-up (components/signup-form.blade.php). */
export default function SignUp() {
  const { signUp } = useAuth();
  const [f, setF] = useState({ name: '', email: '', phone: '', password: '', referral_code: '' });
  const [terms, setTerms] = useState(false);
  const [showReferral, setShowReferral] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const emailRef = useRef<TextInputInstance>(null);
  const phoneRef = useRef<TextInputInstance>(null);
  const passwordRef = useRef<TextInputInstance>(null);
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

  const submit = async () => {
    const local: Record<string, string> = {};
    if (!f.name.trim()) local.name = 'Enter your name.';
    if (!f.email.trim()) local.email = 'Enter your email.';
    if (!/^[6-9]\d{9}$/.test(f.phone.replace(/\D/g, '').slice(-10))) local.phone = 'Enter a 10-digit Indian mobile number.';
    if (f.password.length < 8) local.password = 'Use at least 8 characters.';
    if (!terms) local.terms = 'Please accept the Terms and Privacy policy.';
    setErrors(local);
    if (Object.keys(local).length) return;
    setBusy(true);
    try {
      await signUp({
        name: f.name.trim(),
        email: f.email.trim().toLowerCase(),
        phone: f.phone.replace(/\D/g, '').slice(-10),
        password: f.password,
        password_confirmation: f.password,
        referral_code: f.referral_code.trim() || undefined,
      });
    } catch (e) {
      const err = e as ApiError;
      setErrors(Object.keys(err.fieldErrors ?? {}).length ? err.fieldErrors : { form: err.message });
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthShell back title="Create your account" subtitle="Unlimited GST invoices. Free during beta.">
      <TextField label="Your name" value={f.name} onChangeText={set('name')} error={errors.name} autoComplete="name" textContentType="name" returnKeyType="next" onSubmitEditing={() => emailRef.current?.focus()} />
      <TextField ref={emailRef} label="Email" value={f.email} onChangeText={set('email')} error={errors.email} autoCapitalize="none" keyboardType="email-address" autoComplete="email" textContentType="emailAddress" returnKeyType="next" onSubmitEditing={() => phoneRef.current?.focus()} />
      <TextField
        ref={phoneRef}
        label="Mobile number"
        value={f.phone}
        onChangeText={set('phone')}
        error={errors.phone}
        keyboardType="phone-pad"
        autoComplete="tel"
        textContentType="telephoneNumber"
        maxLength={11}
        hint="10-digit Indian mobile number, for WhatsApp invoices."
        prefix={<AppText style={{ fontFamily: fonts.bold, fontSize: 16 }}>+91</AppText>}
        returnKeyType="next"
        onSubmitEditing={() => passwordRef.current?.focus()}
      />
      <TextField ref={passwordRef} label="Password" value={f.password} onChangeText={set('password')} error={errors.password} secureTextEntry autoComplete="new-password" textContentType="newPassword" hint="At least 8 characters." returnKeyType="done" />
      {showReferral ? (
        <TextField label="Referral code (optional)" value={f.referral_code} onChangeText={set('referral_code')} autoCapitalize="characters" error={errors.referral_code} />
      ) : (
        <Button label="Have a referral code?" kind="ghost" size="sm" icon="add" onPress={() => setShowReferral(true)} style={{ alignSelf: 'flex-start' }} />
      )}
      <Check on={terms} onToggle={() => setTerms((t) => !t)} label="I agree to the Terms and Privacy policy">
        <AppText style={{ fontSize: 14, lineHeight: 20 }} color={colors.textBody}>
          I agree to the{' '}
          <AppText style={{ fontFamily: fonts.bold, fontSize: 14 }} color={colors.tealDark} onPress={() => WebBrowser.openBrowserAsync(LINKS.terms)}>
            Terms
          </AppText>{' '}
          and{' '}
          <AppText style={{ fontFamily: fonts.bold, fontSize: 14 }} color={colors.tealDark} onPress={() => WebBrowser.openBrowserAsync(LINKS.privacy)}>
            Privacy policy
          </AppText>
        </AppText>
      </Check>
      {errors.terms ? (
        <AppText variant="small" color={colors.redText}>
          {errors.terms}
        </AppText>
      ) : null}
      {errors.form ? (
        <AppText variant="small" color={colors.redText}>
          {errors.form}
        </AppText>
      ) : null}
      <Button label="Create account" onPress={submit} loading={busy} />
    </AuthShell>
  );
}
