import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/api_exception.dart';
import '../../core/state/session_controller.dart';
import '../../core/widgets/common.dart';
import 'auth_scaffold.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  bool _googleLoading = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).loginWithEmail(_email.text, _password.text);
      if (mounted && context.canPop()) context.pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _google() async {
    setState(() => _googleLoading = true);
    try {
      final ok = await ref.read(sessionProvider.notifier).loginWithGoogle();
      if (ok && mounted && context.canPop()) context.pop();
    } catch (e) {
      if (mounted) showMessage(context, errorText(e));
    } finally {
      if (mounted) setState(() => _googleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthScaffold(
    title: 'Que bom ver você',
    subtitle: 'Entre para continuar sua jornada de fé.',
    children: [
      Form(
        key: _form,
        child: AutofillGroup(
          child: Column(
            children: [
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(labelText: 'E-mail', prefixIcon: Icon(Icons.mail_outline_rounded)),
                validator: validateEmail,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _password,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.password],
                onFieldSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: 'Senha',
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  suffixIcon: IconButton(
                    tooltip: _obscure ? 'Mostrar senha' : 'Ocultar senha',
                    icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
                validator: (v) => (v ?? '').isEmpty ? 'Informe sua senha.' : null,
              ),
            ],
          ),
        ),
      ),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton(onPressed: () => context.push('/esqueci-senha'), child: const Text('Esqueci minha senha')),
      ),
      const SizedBox(height: 4),
      if (_error != null) FormError(_error!),
      FilledButton(
        onPressed: _loading ? null : _submit,
        child: _loading ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Entrar'),
      ),
      const OrDivider(),
      GoogleButton(onPressed: _google, loading: _googleLoading, label: 'Entrar com o Google'),
      const SizedBox(height: 20),
      Center(
        child: TextButton(onPressed: () => context.pushReplacement('/cadastrar'), child: const Text('Ainda não tem conta? Criar agora')),
      ),
    ],
  );
}
