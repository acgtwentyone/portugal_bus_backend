@extends('layouts.legal')

@section('title', 'Política de Privacidade')

@section('content')
    <h1>Política de Privacidade</h1>
    <p>Esta Política de Privacidade descreve como as suas informações pessoais são recolhidas, utilizadas e partilhadas quando utiliza a aplicação Porto:Bus.</p>

    <h2>1. Informações que Recolhemos</h2>
    <ul>
        <li><strong>Informações de Dispositivo:</strong> Modelo do telemóvel, sistema operativo e identificadores únicos anónimos.</li>
        <li><strong>Dados de Localização:</strong> Necessários para o funcionamento principal da app.</li>
        <li><strong>Logs de Erro:</strong> Para nos ajudar a corrigir bugs e melhorar a estabilidade.</li>
    </ul>

    <h2>2. Como Utilizamos os seus Dados</h2>
    <p>Utilizamos os dados recolhidos para:</p>
    <ul>
        <li>Fornecer e manter o serviço de transporte em tempo real.</li>
        <li>Personalizar a sua experiência de utilizador.</li>
        <li>Melhorar as funcionalidades e o desempenho da aplicação.</li>
    </ul>

    <h2>3. Partilha de Dados</h2>
    <p>Não vendemos nem partilhamos os seus dados pessoais com terceiros para fins de marketing. Podemos partilhar dados agregados e anónimos com parceiros de transporte para melhoria da rede de transportes.</p>

    <h2>4. Os Seus Direitos</h2>
    <p>De acordo com o RGPD, tem o direito de aceder, retificar ou apagar quaisquer dados que tenhamos sobre si. Para tal, entre em contacto connosco através da aplicação.</p>

    <h2>5. Contacto</h2>
    <p>Se tiver dúvidas sobre esta política, pode contactar-nos através do suporte oficial da Portugal Bus.</p>

    <p style="margin-top: 3rem; font-size: 0.8rem;">Última atualização: {{ date('d/m/Y') }}</p>
@endsection
