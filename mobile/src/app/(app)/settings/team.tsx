import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import type { TeamMember, TeamRole } from '@/api/workspace';
import { useAuth } from '@/auth/auth-context';
import { ActionSheet, SheetAction } from '@/components/action-sheet';
import { AppText } from '@/components/app-text';
import { Avatar, Card, EmptyState, ErrorState, Loading, Overline, Row } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { TextField } from '@/components/text-field';
import { useCompanies, useLeaveBusiness, useResendInvite, useTeam, useTeamAction } from '@/hooks/workspace';
import { FormSheet, RoleOptions } from '@/screens/settings/role-sheet';
import { fieldErrors, FormError, Notice, useCan } from '@/screens/settings/shared';
import { colors } from '@/theme';
import { date } from '@/utils/format';
import { shareText } from '@/utils/share';

/** The owner manages the team; a member sees the businesses they joined and may leave them. */
export default function TeamRoute() {
  const owner = useCan('team');
  return owner ? <OwnerTeam /> : <MemberView />;
}

function OwnerTeam() {
  const q = useTeam();
  const act = useTeamAction();
  const resend = useResendInvite();
  const [invite, setInvite] = useState(false);
  const [menu, setMenu] = useState<TeamMember | null>(null);
  const [roleFor, setRoleFor] = useState<TeamMember | null>(null);
  const roles = q.data?.meta.roles ?? [];
  const members = q.data?.data ?? [];

  const doResend = (m: TeamMember) =>
    resend.mutate(m.id, {
      onSuccess: (r) =>
        Alert.alert(
          r.ok ? 'Invitation sent' : r.status === 'throttled' ? 'Sent a moment ago' : 'Already joined',
          r.message || (r.retry_after_minutes ? `You can send it again in ${r.retry_after_minutes} minutes.` : ''),
        ),
      onError: (e) => Alert.alert('Could not send', (e as Error).message),
    });

  const doRemove = (m: TeamMember) =>
    Alert.alert(`Remove ${m.user?.name ?? m.email}?`, m.status === 'pending' ? 'The invite link will stop working.' : 'They will lose access to this business straight away.', [
      { text: 'Keep', style: 'cancel' },
      { text: 'Remove', style: 'destructive', onPress: () => act.mutate({ type: 'remove', id: m.id }, { onError: (e) => Alert.alert('Could not remove', (e as Error).message) }) },
    ]);

  const actions = (m: TeamMember): SheetAction[] => [
    { key: 'role', label: 'Change role', hint: `Now: ${m.role_label}`, icon: 'swap-horizontal', onPress: () => setRoleFor(m) },
    ...(m.status === 'pending'
      ? [
          { key: 'resend', label: 'Send invitation again', hint: m.email, icon: 'mail-outline' as const, onPress: () => doResend(m) },
          ...(m.invite_url ? [{ key: 'share', label: 'Share invite link', hint: 'WhatsApp, SMS…', icon: 'share-social-outline' as const, onPress: () => shareText(inviteMessage(m.invite_url!)) }] : []),
        ]
      : []),
    { key: 'remove', label: m.status === 'pending' ? 'Cancel invitation' : 'Remove access', icon: 'person-remove-outline', danger: true, onPress: () => doRemove(m) },
  ];

  const body = q.isLoading ? (
    <Loading />
  ) : q.error ? (
    <ErrorState message={(q.error as Error).message} onRetry={() => q.refetch()} />
  ) : !members.length ? (
    <EmptyState icon="people-outline" title="Work together" body="Invite your accountant or billing staff. Each person gets their own login and only what their role allows." action="Invite someone" onAction={() => setInvite(true)} />
  ) : (
    <>
      <Card style={{ paddingVertical: 2 }}>
        {members.map((m, i) => (
          <Row
            key={m.id}
            divider={i < members.length - 1}
            onPress={() => setMenu(m)}
            accessibilityLabel={`${m.user?.name ?? m.email}, ${m.role_label}, ${m.status === 'pending' ? 'invitation pending' : 'active'}. Options`}
            leading={<Avatar name={m.user?.name ?? m.email} size={40} />}
            title={m.user?.name ?? m.email}
            subtitle={m.status === 'pending' ? `Invited ${date(m.invited_at)} · not joined yet` : `${m.email} · joined ${date(m.accepted_at)}`}
            trailing={
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <Pill label={m.role_label} tone="info" />
                {m.status === 'pending' ? <Pill label="Pending" tone="due" /> : null}
              </View>
            }
          />
        ))}
      </Card>
      <Overline>Roles</Overline>
      <Card>
        {roles.map((r) => (
          <View key={r.key} style={{ gap: 2 }}>
            <AppText variant="label">{r.label}</AppText>
            <AppText variant="small" color={colors.textMuted}>
              {r.help}
            </AppText>
          </View>
        ))}
      </Card>
    </>
  );

  return (
    <Screen
      nav="back"
      title="Team"
      subtitle="Who can work in this business"
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      footer={members.length ? <Button label="Invite team member" icon="person-add-outline" onPress={() => setInvite(true)} /> : undefined}
    >
      {body}

      <ActionSheet visible={!!menu} title={menu?.user?.name ?? menu?.email ?? ''} subtitle={menu ? `${menu.role_label} · ${menu.status === 'pending' ? 'invitation pending' : 'active'}` : undefined} actions={menu ? actions(menu) : []} onClose={() => setMenu(null)} />
      {invite ? <InviteSheet roles={roles} onClose={() => setInvite(false)} /> : null}
      {roleFor ? <ChangeRoleSheet member={roleFor} roles={roles} onClose={() => setRoleFor(null)} /> : null}
    </Screen>
  );
}

const inviteMessage = (url: string) => `You're invited to work with me on Apna Invoice. Join here: ${url}`;

function InviteSheet({ roles, onClose }: { roles: TeamRole[]; onClose: () => void }) {
  const act = useTeamAction();
  const [email, setEmail] = useState('');
  const [role, setRole] = useState(roles.find((r) => r.key === 'staff')?.key ?? roles[0]?.key ?? 'staff');
  const errors = fieldErrors(act.error);
  const submit = () =>
    act.mutate(
      { type: 'invite', email: email.trim().toLowerCase(), role },
      {
        onSuccess: (message) => {
          onClose();
          Alert.alert('Invitation ready', message);
        },
      },
    );
  return (
    <FormSheet
      visible
      title="Invite team member"
      subtitle="They sign in with this email and see only what the role allows."
      onClose={onClose}
      footer={
        <>
          <FormError error={act.error} />
          <Button label="Send invitation" icon="send" loading={act.isPending} disabled={!email.trim().includes('@')} onPress={submit} />
        </>
      }
    >
      <TextField label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} autoFocus placeholder="name@example.com" error={errors.email} />
      <AppText variant="label" color={colors.textBody}>
        Role
      </AppText>
      <RoleOptions roles={roles} value={role} onChange={setRole} error={errors.role} />
    </FormSheet>
  );
}

function ChangeRoleSheet({ member, roles, onClose }: { member: TeamMember; roles: TeamRole[]; onClose: () => void }) {
  const act = useTeamAction();
  const [role, setRole] = useState(member.role);
  const errors = fieldErrors(act.error);
  return (
    <FormSheet
      visible
      title="Change role"
      subtitle={member.user?.name ?? member.email}
      onClose={onClose}
      footer={
        <>
          <FormError error={act.error} />
          <Button
            label="Save role"
            icon="checkmark"
            loading={act.isPending}
            disabled={role === member.role}
            onPress={() =>
              act.mutate(
                { type: 'role', id: member.id, role },
                {
                  onSuccess: (message) => {
                    onClose();
                    Alert.alert('Role changed', message);
                  },
                },
              )
            }
          />
        </>
      }
    >
      <RoleOptions roles={roles} value={role} onChange={setRole} error={errors.role} />
    </FormSheet>
  );
}

/** A team member: the businesses they joined, each with "Leave business". */
function MemberView() {
  const { company } = useAuth();
  const list = useCompanies();
  const leave = useLeaveBusiness();
  const memberships = list.data?.meta.memberships ?? [];

  const confirmLeave = (memberId: number, name: string) =>
    Alert.alert(`Leave ${name}?`, 'You will lose access to its invoices and customers. The owner can invite you again.', [
      { text: 'Stay', style: 'cancel' },
      {
        text: 'Leave',
        style: 'destructive',
        onPress: () =>
          leave.mutate(memberId, {
            onSuccess: (r) => {
              Alert.alert('Done', r.message);
              router.replace('/');
            },
            onError: (e) => Alert.alert('Could not leave', (e as Error).message),
          }),
      },
    ]);

  return (
    <Screen nav="back" title="Team" subtitle={company?.name} onRefresh={() => list.refetch()} refreshing={list.isRefetching}>
      <Notice text="Only the owner of this business can invite people or change roles." />
      {list.isLoading ? (
        <Loading />
      ) : list.error ? (
        <ErrorState message={(list.error as Error).message} onRetry={() => list.refetch()} />
      ) : memberships.length ? (
        <>
          <Overline>Businesses you joined</Overline>
          <Card>
            {memberships.map((m) => (
              <View key={m.member_id} style={{ gap: 10 }}>
                <Row divider={false} leading={<Avatar name={m.name} size={40} />} title={m.name} subtitle={m.company_id === company?.id ? 'Open now' : m.gstin ?? undefined} trailing={<Pill label={m.role_label} tone="info" />} />
                <Button label="Leave business" kind="danger" icon="exit-outline" size="md" loading={leave.isPending && leave.variables === m.member_id} onPress={() => confirmLeave(m.member_id, m.name)} />
              </View>
            ))}
          </Card>
        </>
      ) : (
        <EmptyState icon="people-outline" title="No team access" body="You have not joined anyone else’s business." />
      )}
    </Screen>
  );
}
