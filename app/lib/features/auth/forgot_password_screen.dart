import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/brand/brand.dart';
import '../../core/state/session_controller.dart';
import '../../core/widgets/common.dart';
import 'auth_scaffold.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _loading = false;
  bool _sent = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      await ref.read(sessionProvider.notifier).forgotPassword(_email.text);
      setState(() => _sent = true);
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(
    title: 'Recuperar senha',
    subtitle: 'Enviaremos um link para você criar uma nova senha.',
    scene: SkyScene.noite,
    children: [
      if (_sent)
        const EmptyView(
          icon: Icons.mark_email_read_rounded,
          title: 'Confira seu e-mail',
          message: 'Se o e-mail estiver cadastrado, o link chega em instantes. Veja também a caixa de spam.',
        )
      else ...[
        Form(
          key: _form,
          child: TextFormField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
            decoration: const InputDecoration(labelText: 'E-mail', prefixIcon: Icon(Icons.mail_outline_rounded)),
            validator: validateEmail,
            onFieldSubmitted: (_) => _submit(),
          ),
        ),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Enviar link'),
        ),
      ],
    ],
  );
}
