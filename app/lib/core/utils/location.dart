import 'dart:async';

import 'package:geolocator/geolocator.dart';

/// Erro de localização com mensagem pronta para o usuário.
class LocationFailure implements Exception {
  const LocationFailure(this.message);

  final String message;

  @override
  String toString() => message;
}

/// Posição atual com limite de tempo: sem sinal de GPS (ambiente fechado,
/// aparelho recém-ligado), usa a última posição conhecida em vez de travar a tela.
Future<Position> currentPosition({LocationAccuracy accuracy = LocationAccuracy.high, Duration timeLimit = const Duration(seconds: 15)}) async {
  if (!await Geolocator.isLocationServiceEnabled()) {
    throw const LocationFailure('Ative a localização do aparelho.');
  }
  var permission = await Geolocator.checkPermission();
  if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
  if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
    throw const LocationFailure('Permita o acesso à localização nas configurações do aparelho.');
  }
  try {
    return await Geolocator.getCurrentPosition(
      locationSettings: LocationSettings(accuracy: accuracy, timeLimit: timeLimit),
    );
  } on TimeoutException {
    final last = await Geolocator.getLastKnownPosition();
    if (last != null) return last;
    throw const LocationFailure('Não foi possível obter a localização. Tente novamente em um local aberto.');
  }
}
