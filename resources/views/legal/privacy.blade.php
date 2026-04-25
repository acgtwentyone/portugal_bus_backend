@extends('layouts.legal')

@section('title', 'Dados e Privacidade')

@section('content')
    <h1>Dados e Privacidade</h1>
    <p>No Porto:Bus, levamos a sua privacidade a sério. Esta secção explica como lidamos com os seus dados de utilização e localização para lhe proporcionar a melhor experiência de transporte.</p>

    <h2>1. Transparência de Dados</h2>
    <p>Acreditamos que deve saber sempre que dados estamos a utilizar. O Porto:Bus foca-se na recolha mínima necessária para o funcionamento da aplicação.</p>

    <h2>2. Localização</h2>
    <p>Para mostrar os autocarros e paragens mais próximos de si, necessitamos de aceder à sua localização em tempo real enquanto utiliza a aplicação. Estes dados não são guardados nos nossos servidores de forma identificável.</p>

    <h2>3. Segurança</h2>
    <p>Utilizamos protocolos de segurança modernos para garantir que qualquer comunicação entre a sua aplicação e os nossos servidores seja cifrada e segura.</p>

    <h2>4. Controlo do Utilizador</h2>
    <p>Pode gerir as permissões de localização e notificações diretamente nas definições do seu dispositivo móvel a qualquer momento.</p>

    <p style="margin-top: 3rem; font-size: 0.8rem;">Última atualização: {{ date('d/m/Y') }}</p>
@endsection
