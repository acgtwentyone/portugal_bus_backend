@extends('layouts.legal')

@section('title', 'Termos e Condições')

@section('content')
    <h1>Termos e Condições</h1>
    <p>Bem-vindo ao Porto:Bus. Ao utilizar a nossa aplicação, concorda com os seguintes termos e condições de utilização.</p>

    <h2>1. Uso da Aplicação</h2>
    <p>A aplicação Porto:Bus destina-se a fornecer informações em tempo real e planeamento de rotas para transportes públicos. O uso da aplicação deve ser pessoal e não comercial.</p>

    <h2>2. Exatidão da Informação</h2>
    <p>Embora nos esforcemos por fornecer dados precisos da STCP e outros operadores, não podemos garantir a exatidão absoluta dos tempos de chegada, que podem variar devido ao trânsito e outros fatores externos.</p>

    <h2>3. Propriedade Intelectual</h2>
    <p>Todos os conteúdos, design e código da aplicação Porto:Bus são propriedade da Portugal Bus ou dos seus licenciadores.</p>

    <h2>4. Modificações</h2>
    <p>Reservamo-nos o direito de modificar estes termos a qualquer momento. O uso continuado da aplicação constitui a aceitação dos novos termos.</p>

    <h2>5. Limitação de Responsabilidade</h2>
    <p>A Portugal Bus não se responsabiliza por quaisquer atrasos ou inconveniências causados pela utilização das informações fornecidas pela aplicação.</p>

    <p style="margin-top: 3rem; font-size: 0.8rem;">Última atualização: {{ date('d/m/Y') }}</p>
@endsection
